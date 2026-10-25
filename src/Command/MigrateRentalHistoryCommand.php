<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\Enum\Action;
use BikeShare\Db\DbInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:migrate_rental_history', description: 'Fill and normalize unambiguous historical rental pairs')]
class MigrateRentalHistoryCommand extends Command
{
    // Separate forced actions were introduced in November 2014 (5d64acd).
    private const LEGACY_RETURN_BEFORE = '2014-11-12 00:00:00';

    private array $reviewedDeletions = [];
    private array $historicalCompletions = [];
    private array $historicalEndIds = [];
    private array $legacyForcedReturns = [];
    private array $chainStarts = [];
    private array $repeatedReturns = [];
    private array $reassignedReturns = [];
    private array $technicalEvents = [];
    private array $technicalLinks = [];
    private array $revertPairs = [];
    private array $reorderedPairs = [];

    public function __construct(private readonly DbInterface $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('bike', null, InputOption::VALUE_REQUIRED, 'Process only this bike number')
            ->addOption(
                'repairs',
                null,
                InputOption::VALUE_REQUIRED,
                'JSON file containing reviewed repairs and original rows',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without modifying database')
            ->setHelp('Scans the full history. Add -v for event IDs and skip reasons.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bike = $input->getOption('bike');
        $bikeNumber = $bike === null ? null : filter_var($bike, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($bikeNumber === false) {
            $io->error('--bike must be a positive integer.');

            return Command::INVALID;
        }

        $apply = !(bool)$input->getOption('dry-run');
        $io->writeln($apply ? 'Migrating rental history.' : 'Previewing rental history; no changes will be written.');
        $this->legacyForcedReturns = [];
        $this->chainStarts = [];
        $this->repeatedReturns = [];
        $this->reassignedReturns = [];
        $this->technicalEvents = [];
        $this->technicalLinks = [];
        $this->revertPairs = [];
        $this->reorderedPairs = [];
        $this->reviewedDeletions = [];
        $this->historicalCompletions = [];
        $this->historicalEndIds = [];
        $repairs = $this->prepareReviewedRepairs($input->getOption('repairs'), $bikeNumber);
        foreach ($repairs['delete'] as $row) {
            $this->reviewedDeletions[(int)$row['id']] = true;
            $output->writeln(sprintf('Delete reviewed history %d', $row['id']), OutputInterface::VERBOSITY_VERBOSE);
            if ($apply) {
                $this->deleteReviewedRow($row);
            }
        }
        foreach ($repairs['insert'] as $repair) {
            $row = $repair['event'];
            $this->historicalCompletions[(int)$row['pairActionId']] = $row;
            $output->writeln(
                sprintf('Restore %s -> rent %d at %s', $row['action'], $row['pairActionId'], $row['time']),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->insertReviewedCompletion($repair);
            }
        }
        $this->findHistoricalCompletions($bikeNumber);
        $reverts = $this->findTechnicalRevertEvents($bikeNumber);
        $this->repeatedReturns = $this->findRepeatedReturnLinks($bikeNumber);
        $this->chainStarts = $this->findInterruptedStartLinks($bikeNumber);
        $this->chainStarts += $this->findStartsWithOwnPair($bikeNumber);
        $this->reassignedReturns = $this->findMislinkedReturns($bikeNumber);
        $this->repeatedReturns += $this->findRepeatedReturnLinks($bikeNumber);
        $this->chainStarts += $this->findStartsWithOwnPair($bikeNumber);
        $this->chainStarts += $this->findChainStarts($bikeNumber);
        $this->revertPairs = $this->findRevertPairs($reverts);
        $this->legacyForcedReturns = $this->findLegacyForcedReturns($bikeNumber);
        foreach ($this->legacyForcedReturns as $event) {
            $output->writeln(
                sprintf(
                    'Normalize legacy return %d: %s -> %s',
                    $event['id'],
                    Action::RETURN->value,
                    Action::FORCE_RETURN->value,
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->updateLegacyReturnAction($event);
            }
        }
        $revertsChanged = 0;
        foreach ($this->revertPairs as $correction) {
            if ($correction['event']['pairActionId'] !== null) {
                continue;
            }
            ++$revertsChanged;
            $output->writeln(
                sprintf('Pair revert %d -> rent %d', $correction['event']['id'], $correction['rentId']),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->updatePair($correction['event'], $correction['rentId']);
            }
        }
        foreach ($this->technicalLinks as $event) {
            $output->writeln(
                sprintf('Clear technical event %d -> event %d', $event['id'], $event['pairActionId']),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->updatePair($event, null);
            }
        }
        foreach ($this->reassignedReturns as $correction) {
            $return = $correction['event'];
            $output->writeln(
                sprintf(
                    'Reassign return %d: rent %d -> rent %d',
                    $return['id'],
                    $return['pairActionId'],
                    $correction['rentId'],
                ),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->updatePair($return, $correction['rentId']);
            }
        }
        foreach ($this->repeatedReturns as $return) {
            $output->writeln(
                sprintf('Clear repeated return %d -> rent %d', $return['id'], $return['pairActionId']),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->updatePair($return, null);
            }
        }
        foreach ($this->chainStarts as $start) {
            $output->writeln(
                sprintf('Clear chain link on rent %d -> return %d', $start['id'], $start['pairActionId']),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->updatePair($start, null);
            }
        }
        $previous = [];
        $changed = 0;
        $unchanged = count($this->historicalCompletions);
        $emptyServiceEvents = 0;
        $skipped = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $bikeId = (int)$event['bikeNum'];
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            if (isset($this->reorderedPairs[$event['id']])) {
                if (in_array($action, [Action::RETURN, Action::FORCE_RETURN], true)) {
                    ++$unchanged;
                    $previous[$bikeId] = $event;
                }
                continue;
            }
            $isFirstEvent = !array_key_exists($bikeId, $previous);
            $start = $previous[$bikeId] ?? null;
            $previous[$bikeId] = $event;
            if (isset($this->technicalEvents[$event['id']]) || isset($this->revertPairs[$event['id']])) {
                continue;
            }
            if ($action === Action::REVERT || $action === null) {
                if ($event['action'] === '' && $bikeId === 0 && (int)$event['userId'] === 0) {
                    ++$emptyServiceEvents;
                    $output->writeln(
                        sprintf('Ignore history %d: empty action without bike or user', $event['id']),
                        OutputInterface::VERBOSITY_VERBOSE,
                    );
                } else {
                    $reason = $action === Action::REVERT
                        ? (isset($reverts[$event['id']]) ? 'unpaired_revert' : 'revert') : 'unknown_action';
                    $this->skip($event, $reason, $skipped, $output);
                }
                continue;
            }
            if (in_array($action, [Action::RENT, Action::FORCE_RENT], true)) {
                if (
                    in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                    && !($start['afterRevert'] ?? false)
                    && !isset($this->historicalCompletions[$start['id']])
                ) {
                    $this->skip($start, 'superseded_start', $skipped, $output);
                }
                // Legacy REVERT writes an extra RENT/RETURN. Do not infer a trip from that pair.
                if (($start['action'] ?? null) === Action::REVERT->value) {
                    $previous[$bikeId]['afterRevert'] = true;
                    $this->skip($event, 'start_after_revert', $skipped, $output);
                }
                continue;
            }
            $reason = $this->invalidPairReason($start, $event);
            if ($reason === 'no_adjacent_start' && $bikeId > 0) {
                if ($isFirstEvent) {
                    $reason = $action === Action::RETURN ? 'initial_return' : 'initial_force_return';
                } elseif (
                    in_array($start['action'] ?? null, [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                ) {
                    $reason = $action === Action::RETURN ? 'return_after_return' : 'force_return_after_return';
                }
            }
            if ($reason !== null) {
                $this->skip($event, $reason, $skipped, $output);
                continue;
            }
            if ($start['pairActionId'] === null && $event['pairActionId'] !== null) {
                ++$unchanged;
                continue;
            }
            $output->writeln(
                sprintf('Pair return %d -> rent %d', $event['id'], $start['id']),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                // Persist the closing link first; an interrupted normalization can be resumed safely.
                if ($event['pairActionId'] === null) {
                    $this->updatePair($event, (int)$start['id']);
                }
                if ($start['pairActionId'] !== null) {
                    $this->updatePair($start, null);
                }
            }
            ++$changed;
        }

        foreach ($previous as $event) {
            if (
                in_array($event['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                && !($event['afterRevert'] ?? false)
                && !isset($this->historicalCompletions[$event['id']])
            ) {
                $this->skip($event, 'no_return', $skipped, $output);
            }
        }

        $io->table(['Result', 'Count'], [
            [$apply ? 'Reviewed rows deleted' : 'Reviewed rows to delete', count($repairs['delete'])],
            [
                $apply ? 'Historical completions inserted' : 'Historical completions to insert',
                count($repairs['insert']),
            ],
            ['Recognized historical completions', count($this->historicalCompletions)],
            [
                $apply ? 'Legacy return actions normalized' : 'Legacy return actions to normalize',
                count($this->legacyForcedReturns),
            ],
            [$apply ? 'Cancellations linked' : 'Cancellations to link', $revertsChanged],
            ['Already linked cancellations', count($this->revertPairs) - $revertsChanged],
            ['Recognized technical revert events', count($this->technicalEvents)],
            [$apply ? 'Technical links cleared' : 'Technical links to clear', count($this->technicalLinks)],
            [$apply ? 'Return links reassigned' : 'Return links to reassign', count($this->reassignedReturns)],
            [
                $apply ? 'Repeated return links cleared' : 'Repeated return links to clear',
                count($this->repeatedReturns),
            ],
            [$apply ? 'Chain links cleared' : 'Chain links to clear', count($this->chainStarts)],
            [$apply ? 'Pairs updated' : 'Pairs to update', $changed],
            ['Already linked', $unchanged],
            ['Ignored empty actions without bike or user', $emptyServiceEvents],
            ['Skipped returns/events', array_sum($skipped)],
        ]);
        foreach ($skipped as $reason => $count) {
            $io->writeln(sprintf('%s: %d', $reason, $count));
        }

        return Command::SUCCESS;
    }

    private function prepareReviewedRepairs(?string $path, ?int $bikeNumber): array
    {
        $result = ['delete' => [], 'insert' => []];
        if ($path === null) {
            return $result;
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Cannot read the reviewed repairs file.');
        }
        $plan = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($plan) || ($plan['version'] ?? null) !== 1) {
            throw new \RuntimeException('Unsupported reviewed repairs format.');
        }
        // Reviewed snapshots use UTC even when the server's default timezone differs.
        $this->db->query("SET time_zone = '+00:00'");
        $seen = [];
        foreach ($plan['delete'] ?? [] as $repair) {
            $row = $repair['event'];
            if ($bikeNumber !== null && (int)$row['bikeNum'] !== $bikeNumber) {
                continue;
            }
            $id = (int)$row['id'];
            if (isset($seen[$id])) {
                throw new \RuntimeException('Duplicate reviewed deletion.');
            }
            $seen[$id] = true;
            if ($this->readHistoryRow($id) === null) {
                continue;
            }
            $this->assertReviewedRow($row);
            if (!in_array($row['pairActionId'], [null, 0], true) || $this->hasOtherReferences([$id], [])) {
                throw new \RuntimeException(sprintf('History %d is referenced; cannot delete it.', $id));
            }
            if ($repair['reason'] === 'duplicate_start') {
                $keep = $repair['keep'];
                $this->assertReviewedRow($keep);
                $seconds = strtotime($keep['time']) - strtotime($row['time']);
                if (
                    $row['action'] !== Action::RENT->value || $keep['action'] !== Action::RENT->value
                    || (int)$row['bikeNum'] <= 0 || $row['bikeNum'] !== $keep['bikeNum']
                    || (int)$row['userId'] <= 0 || $row['userId'] !== $keep['userId']
                    || (int)$keep['id'] <= $id || $seconds < 0 || $seconds > 2
                    || $keep['pairActionId'] !== null || $this->nextLifecycleId($row) !== (int)$keep['id']
                ) {
                    throw new \RuntimeException('The reviewed duplicate no longer matches its retained start.');
                }
            } elseif ($repair['reason'] === 'unknown_action') {
                if ($row['action'] !== '') {
                    throw new \RuntimeException('The reviewed unknown action is no longer empty.');
                }
            } elseif (
                $repair['reason'] !== 'failed_qr_return' || (int)$row['bikeNum'] !== 0
                || $row['action'] !== Action::RETURN->value
            ) {
                throw new \RuntimeException('Unsupported reviewed deletion.');
            }
            $result['delete'][] = $row;
        }
        $seen = [];
        foreach ($plan['insert'] ?? [] as $repair) {
            $row = $repair['event'];
            $start = $repair['start'];
            $next = $repair['next'];
            if ($bikeNumber !== null && (int)$row['bikeNum'] !== $bikeNumber) {
                continue;
            }
            $fields = array_keys($row);
            sort($fields);
            if ($fields !== ['action', 'bikeNum', 'pairActionId', 'parameter', 'standId', 'time', 'userId']) {
                throw new \RuntimeException('A reviewed completion must contain every field except its generated ID.');
            }
            $time = \DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                (string)$row['time'],
                new \DateTimeZone('UTC'),
            );
            if (
                $time === false || $time->format('Y-m-d H:i:s') !== $row['time']
                || !is_int($row['userId']) || !is_int($row['bikeNum']) || !is_int($row['pairActionId'])
                || !is_int($row['standId']) || !is_string($row['parameter'])
            ) {
                throw new \RuntimeException('Invalid reviewed completion field types or timestamp.');
            }
            $deletedIds = array_column($result['delete'], 'id');
            if (in_array($start['id'], $deletedIds, true) || in_array($next['id'], $deletedIds, true)) {
                throw new \RuntimeException('A completion cannot depend on a row scheduled for deletion.');
            }
            $startId = (int)$start['id'];
            if (isset($seen[$startId])) {
                throw new \RuntimeException('Duplicate reviewed completion.');
            }
            $seen[$startId] = true;
            $this->assertReviewedRow($start);
            $this->assertReviewedRow($next);
            $validStart = in_array($start['action'], [Action::RENT->value, Action::FORCE_RENT->value], true);
            $validNext = in_array($next['action'], [Action::RENT->value, Action::FORCE_RENT->value], true);
            if (
                !$validStart || !$validNext || (int)$start['userId'] <= 0 || (int)$row['userId'] <= 0
                || (int)$start['bikeNum'] <= 0 || $row['bikeNum'] !== $start['bikeNum']
                || $row['bikeNum'] !== $next['bikeNum'] || $start['pairActionId'] !== null
                || (int)$row['pairActionId'] !== $startId || (int)$next['id'] <= $startId
                || $row['time'] < $start['time'] || $row['time'] > $next['time']
                || !in_array($row['action'], [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                || ($row['action'] === Action::RETURN->value && $row['userId'] !== $start['userId'])
                || $this->nextLifecycleId($start) !== (int)$next['id']
                || (int)$row['standId'] <= 0 || (string)$row['standId'] !== $row['parameter']
            ) {
                throw new \RuntimeException(sprintf('Invalid reviewed completion for history %d.', $startId));
            }
            $existing = $this->db->query(
                'SELECT * FROM history WHERE pairActionId = :id',
                ['id' => $startId],
            )->fetchAllAssoc();
            if ($existing !== []) {
                if (count($existing) !== 1 || !$this->sameHistoryFields($row, $existing[0])) {
                    throw new \RuntimeException(sprintf('History %d already has a different completion.', $startId));
                }
                continue;
            }
            $result['insert'][] = $repair;
        }

        return $result;
    }

    private function readHistoryRow(int $id): ?array
    {
        return $this->db->query('SELECT * FROM history WHERE id = :id', ['id' => $id])->fetchAssoc();
    }

    private function sameHistoryFields(array $expected, array $actual): bool
    {
        foreach ($expected as $field => $value) {
            if (!array_key_exists($field, $actual) || $actual[$field] !== $value) {
                return false;
            }
        }

        return true;
    }

    private function assertReviewedRow(array $row): void
    {
        $fields = array_keys($row);
        sort($fields);
        if ($fields !== ['action', 'bikeNum', 'id', 'pairActionId', 'parameter', 'standId', 'time', 'userId']) {
            throw new \RuntimeException('A reviewed original must contain every history field.');
        }
        $actual = $this->readHistoryRow((int)$row['id']);
        if ($actual === null || !$this->sameHistoryFields($row, $actual)) {
            throw new \RuntimeException(sprintf('History %d differs from the reviewed original.', $row['id']));
        }
    }

    private function nextLifecycleId(array $start): ?int
    {
        $next = $this->db->query(
            'SELECT id FROM history WHERE bikeNum = :bike AND id > :id
             AND action IN (:rent, :forceRent, :return, :forceReturn, :revert) ORDER BY id LIMIT 1',
            [
                'bike' => $start['bikeNum'], 'id' => $start['id'],
                'rent' => Action::RENT->value, 'forceRent' => Action::FORCE_RENT->value,
                'return' => Action::RETURN->value, 'forceReturn' => Action::FORCE_RETURN->value,
                'revert' => Action::REVERT->value,
            ],
        )->fetchAssoc();

        return $next === null ? null : (int)$next['id'];
    }

    private function deleteReviewedRow(array $row): void
    {
        $this->assertReviewedRow($row);
        // The reviewed file retains the original row for recovery.
        $affected = $this->db->query(
            'DELETE FROM history WHERE id = :id AND userId = :userId AND bikeNum = :bikeNum
             AND time = :time AND action = :action AND parameter = :parameter
             AND standId <=> :standId AND pairActionId <=> :pairActionId',
            $row,
        )->rowCount();
        if ($affected !== 1) {
            throw new \RuntimeException(sprintf('History %d changed during deletion.', $row['id']));
        }
    }

    private function insertReviewedCompletion(array $repair): void
    {
        $this->assertReviewedRow($repair['start']);
        $this->assertReviewedRow($repair['next']);
        $row = $repair['event'];
        $affected = $this->db->query(
            'INSERT INTO history (userId, bikeNum, time, action, parameter, standId, pairActionId)
             SELECT :userId, :bikeNum, :time, :action, :parameter, :standId, :pairActionId FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM history WHERE pairActionId = :startId)',
            $row + ['startId' => $row['pairActionId']],
        )->rowCount();
        if ($affected !== 1) {
            throw new \RuntimeException('The rental was completed during insertion; rerun the preview.');
        }
    }

    private function findHistoricalCompletions(?int $bikeNumber): void
    {
        $previous = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            $start = $previous[$bikeId] ?? null;
            $event['afterRevert'] = ($start['action'] ?? null) === Action::REVERT->value;
            $previous[$bikeId] = $event;
            if (
                $bikeId <= 0 || !in_array($action, [Action::RENT, Action::FORCE_RENT], true)
                || !in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                || $start['afterRevert'] || (int)$start['userId'] <= 0 || $start['pairActionId'] !== null
            ) {
                continue;
            }
            $ends = $this->db->query(
                'SELECT * FROM history WHERE pairActionId = :id',
                ['id' => $start['id']],
            )->fetchAllAssoc();
            if (count($ends) !== 1) {
                continue;
            }
            $end = $ends[0];
            if (
                (int)$end['bikeNum'] !== $bikeId || (int)$end['id'] <= (int)$event['id']
                || !in_array($end['action'], [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                || $end['time'] < $start['time'] || $end['time'] > $event['time']
                || ($end['action'] === Action::RETURN->value && (int)$end['userId'] !== (int)$start['userId'])
                || $this->hasOtherReferences([(int)$end['id']], [])
            ) {
                continue;
            }
            $this->historicalCompletions[(int)$start['id']] = $end;
            $this->historicalEndIds[(int)$end['id']] = true;
        }
    }

    private function findLegacyForcedReturns(?int $bikeNumber): array
    {
        $previous = [];
        $returns = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            $start = $previous[$bikeId] ?? null;
            $event['afterRevert'] = ($start['action'] ?? null) === Action::REVERT->value;
            $previous[$bikeId] = $event;
            if (
                $bikeId <= 0 || $action !== Action::RETURN || !str_starts_with($event['time'], '2014-')
                || $event['time'] >= self::LEGACY_RETURN_BEFORE
                || ($start['action'] ?? null) !== Action::RENT->value || $start['afterRevert']
                || isset($this->technicalEvents[$start['id']]) || isset($this->technicalEvents[$event['id']])
                || (int)$start['userId'] <= 0 || (int)$event['userId'] <= 0
                || (int)$start['userId'] === (int)$event['userId'] || $start['time'] > $event['time']
                || $start['pairActionId'] !== null || (int)$event['pairActionId'] !== (int)$start['id']
            ) {
                continue;
            }
            $ids = [(int)$start['id'], (int)$event['id']];
            if (!$this->hasOtherReferences($ids, $ids)) {
                $returns[(int)$event['id']] = $event;
            }
        }

        return $returns;
    }

    private function updateLegacyReturnAction(array $event): void
    {
        $affected = $this->db->query(
            'UPDATE history SET action = :newAction
             WHERE id = :id AND bikeNum = :bikeNum AND userId = :userId AND action = :oldAction
               AND time = :time AND parameter = :parameter AND pairActionId = :pairActionId',
            [
                'newAction' => Action::FORCE_RETURN->value,
                'id' => $event['id'],
                'bikeNum' => $event['bikeNum'],
                'userId' => $event['userId'],
                'oldAction' => Action::RETURN->value,
                'time' => $event['time'],
                'parameter' => $event['parameter'],
                'pairActionId' => $event['pairActionId'],
            ],
        )->rowCount();
        if ($affected !== 1) {
            throw new \RuntimeException(
                sprintf('History %d changed during migration; rerun the preview.', $event['id']),
            );
        }
    }

    private function findTechnicalRevertEvents(?int $bikeNumber): array
    {
        $recent = [];
        $reverts = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            $events = $recent[$bikeId] ?? [];
            $recent[$bikeId] = array_slice([...$events, $event], -3);
            if ($bikeId <= 0 || $action !== Action::RETURN || count($events) < 2) {
                continue;
            }
            $start = count($events) === 3 ? $events[0] : null;
            [$revert, $technicalStart] = array_slice($events, -2);
            $swapped = $revert['action'] === Action::RENT->value
                && $technicalStart['action'] === Action::REVERT->value;
            if ($swapped) {
                [$revert, $technicalStart] = [$technicalStart, $revert];
            }
            if (
                $revert['action'] !== Action::REVERT->value || $technicalStart['action'] !== Action::RENT->value
                || (int)$technicalStart['userId'] !== (int)$event['userId']
                || !in_array((int)$technicalStart['userId'], [0, (int)$revert['userId']], true)
                || $technicalStart['time'] < $revert['time']
                || ((int)$technicalStart['userId'] !== 0 && ($technicalStart['time'] !== $revert['time']
                    || $event['time'] !== $revert['time']))
                || ($swapped && ((int)$technicalStart['userId'] !== 0 || $event['time'] !== $revert['time']
                    || $technicalStart['time'] !== $revert['time']))
            ) {
                continue;
            }
            $parameters = explode('|', $revert['parameter']);
            if (
                count($parameters) !== 2 || !ctype_digit($parameters[0]) || !ctype_digit($parameters[1])
                || !ctype_digit($technicalStart['parameter']) || !ctype_digit($event['parameter'])
                || (int)$parameters[0] <= 0 || (int)$parameters[0] !== (int)$event['parameter']
                || (int)$parameters[1] !== (int)$technicalStart['parameter']
            ) {
                continue;
            }
            // The matching code and stand identify restoration records even if a legacy timestamp was edited.
            foreach ([$technicalStart, $event] as $technical) {
                $this->technicalEvents[(int)$technical['id']] = [
                    'rentId' => (int)$technicalStart['id'],
                    'time' => max($revert['time'], $technicalStart['time'], $event['time']),
                ];
                if ($technical['pairActionId'] !== null) {
                    $this->technicalLinks[(int)$technical['id']] = $technical;
                }
            }
            $reverts[(int)$revert['id']] = ['event' => $revert, 'start' => $start];
        }

        return $reverts;
    }

    private function findInterruptedStartLinks(?int $bikeNumber): array
    {
        $previous = [];
        $starts = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            if (isset($this->technicalEvents[$event['id']])) {
                continue;
            }
            $start = $previous[$bikeId] ?? null;
            $previous[$bikeId] = $event;
            if (
                $bikeId <= 0 || !in_array($action, [Action::RENT, Action::FORCE_RENT, Action::REVERT], true)
                || !in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                || (int)$start['userId'] <= 0 || $start['pairActionId'] === null || $start['time'] > $event['time']
                || $this->hasOtherReferences([(int)$start['id']], [$event['id']])
            ) {
                continue;
            }
            $oldReturn = $this->db->query(
                'SELECT id, bikeNum, action, time FROM history WHERE id = :id',
                ['id' => $start['pairActionId']],
            )->fetchAssoc();
            if (
                $oldReturn !== null && (int)$oldReturn['bikeNum'] === $bikeId
                && in_array($oldReturn['action'], [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                && $oldReturn['time'] <= $start['time']
                && ((int)$oldReturn['id'] < (int)$start['id'] || $oldReturn['time'] < $start['time'])
            ) {
                $starts[(int)$start['id']] = $start;
            }
        }

        return $starts;
    }

    private function findRevertPairs(array $reverts): array
    {
        $pairs = [];
        foreach ($reverts as $id => $group) {
            $event = $group['event'];
            $start = $group['start'];
            if (
                !in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                || isset($this->technicalEvents[$start['id']]) || (int)$start['userId'] <= 0
                || $start['time'] > $event['time']
                || ($start['pairActionId'] !== null && !isset($this->chainStarts[$start['id']]))
                || ($event['pairActionId'] !== null && (int)$event['pairActionId'] !== (int)$start['id'])
                || $this->hasOtherReferences([(int)$start['id'], $id], [(int)$start['id'], $id])
            ) {
                continue;
            }
            $pairs[$id] = ['event' => $event, 'rentId' => (int)$start['id']];
        }

        return $pairs;
    }

    private function findMislinkedReturns(?int $bikeNumber): array
    {
        $recent = [];
        $corrections = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            $events = $recent[$bikeId] ?? [];
            $event['afterRevert'] = ($events[count($events) - 1]['action'] ?? null) === Action::REVERT->value;
            $recent[$bikeId] = array_slice([...$events, $event], -3);
            if ($bikeId <= 0 || $action !== Action::RETURN || count($events) !== 3) {
                continue;
            }
            [$oldStart, $oldReturn, $start] = $events;
            if (
                !in_array($oldStart['action'], [Action::RENT->value, Action::FORCE_RENT->value], true)
                || !in_array($oldReturn['action'], [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                || !in_array($start['action'], [Action::RENT->value, Action::FORCE_RENT->value], true)
                || $oldStart['afterRevert'] || (int)$oldStart['userId'] <= 0 || (int)$start['userId'] <= 0
                || $oldStart['pairActionId'] !== null
                || (int)$oldReturn['pairActionId'] !== (int)$oldStart['id']
                || (int)$start['pairActionId'] !== (int)$oldReturn['id']
                || (int)$event['pairActionId'] !== (int)$oldStart['id']
                || $oldStart['time'] > $oldReturn['time'] || $oldReturn['time'] > $start['time']
                || $start['time'] > $event['time'] || (int)$start['userId'] !== (int)$event['userId']
                || ($oldReturn['action'] === Action::RETURN->value && $oldStart['userId'] !== $oldReturn['userId'])
            ) {
                continue;
            }
            $ids = [(int)$oldStart['id'], (int)$oldReturn['id'], (int)$event['id']];
            if (
                !$this->hasOtherReferences($ids, [...$ids, (int)$start['id']])
                && $this->hasOnlyLaterParkedReferences($start, $event)
            ) {
                $corrections[(int)$event['id']] = ['event' => $event, 'rentId' => (int)$start['id']];
            }
        }

        return $corrections;
    }

    private function hasOnlyLaterParkedReferences(array $start, array $return): bool
    {
        $references = $this->db->query(
            'SELECT id FROM history WHERE pairActionId = :id',
            ['id' => $start['id']],
        )->fetchAllAssoc();
        if ($references === []) {
            return true;
        }
        $ids = array_map(static fn (array $row): int => (int)$row['id'], $references);
        if (min($ids) <= (int)$return['id']) {
            return false;
        }
        $events = $this->db->query(
            'SELECT id, action, time, pairActionId FROM history
             WHERE bikeNum = :bike AND id > :afterId AND id <= :lastId ORDER BY id',
            ['bike' => $start['bikeNum'], 'afterId' => $return['id'], 'lastId' => max($ids)],
        )->fetchAllAssoc();
        $parkedIds = [];
        $previousTime = $return['time'];
        foreach ($events as $event) {
            $action = Action::tryFrom($event['action']);
            if (in_array($action, [Action::RETURN, Action::FORCE_RETURN], true)) {
                if (
                    $event['time'] < $previousTime
                    || ($event['pairActionId'] !== null && (int)$event['pairActionId'] !== (int)$start['id'])
                ) {
                    return false;
                }
                $previousTime = $event['time'];
                $parkedIds[] = (int)$event['id'];
            } elseif ($action === null || in_array($action, [Action::RENT, Action::FORCE_RENT, Action::REVERT], true)) {
                return false;
            }
        }

        // A reference on another bike or from a service event cannot be explained by parked returns.
        return array_diff($ids, $parkedIds) === [];
    }

    private function findRepeatedReturnLinks(?int $bikeNumber): array
    {
        $previousActions = [];
        $starts = [];
        $closed = [];
        $repeated = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            if ($action === Action::RETURN && isset($this->technicalEvents[$event['id']])) {
                unset($starts[$bikeId]);
                $previousActions[$bikeId] = $action;
                $event['pairActionId'] = $this->technicalEvents[$event['id']]['rentId'];
                $event['time'] = $this->technicalEvents[$event['id']]['time'];
                $closed[$bikeId] = $event;
                continue;
            }
            $afterRevert = ($previousActions[$bikeId] ?? null) === Action::REVERT;
            $previousActions[$bikeId] = $action;
            if (in_array($action, [Action::RENT, Action::FORCE_RENT], true)) {
                unset($closed[$bikeId]);
                $starts[$bikeId] = !$afterRevert && $bikeId > 0 && (int)$event['userId'] > 0 ? $event : null;
                continue;
            }
            if (!in_array($action, [Action::RETURN, Action::FORCE_RETURN], true)) {
                unset($starts[$bikeId], $closed[$bikeId]);
                continue;
            }
            $start = $starts[$bikeId] ?? null;
            unset($starts[$bikeId]);
            if ($start !== null) {
                // Only an existing adjacent closing link can prove a later return link is redundant.
                if (
                    $event['pairActionId'] !== null && (int)$event['pairActionId'] === (int)$start['id']
                    && $start['time'] <= $event['time']
                    && ($action === Action::FORCE_RETURN || (int)$start['userId'] === (int)$event['userId'])
                ) {
                    $closed[$bikeId] = $event;
                }
                continue;
            }
            $closing = $closed[$bikeId] ?? null;
            if (
                $closing !== null
                && ($event['pairActionId'] === null || (int)$event['pairActionId'] === (int)$closing['pairActionId'])
                && $closing['time'] <= $event['time']
            ) {
                if ($event['pairActionId'] !== null) {
                    $repeated[(int)$event['id']] = $event;
                }
                // Parked returns with NULL do not reopen the already closed rental.
                $event['pairActionId'] = $closing['pairActionId'];
                $closed[$bikeId] = $event;
            } else {
                unset($closed[$bikeId]);
            }
        }

        return $repeated;
    }

    private function findStartsWithOwnPair(?int $bikeNumber): array
    {
        $previous = [];
        $starts = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            $start = $previous[$bikeId] ?? null;
            $event['afterRevert'] = ($start['action'] ?? null) === Action::REVERT->value;
            $previous[$bikeId] = $event;
            $reversed = in_array($action, [Action::RENT, Action::FORCE_RENT], true)
                && in_array($start['action'] ?? null, [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                && (int)$start['pairActionId'] === (int)$event['id'];
            if ($reversed) {
                [$start, $event] = [$event, $start];
                $action = Action::tryFrom($event['action']);
            }
            if (
                $bikeId <= 0 || !in_array($action, [Action::RETURN, Action::FORCE_RETURN], true)
                || !in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                || $start['afterRevert'] || (int)$start['userId'] <= 0
                || (!$reversed && $start['pairActionId'] === null)
                || ($reversed && ($event['afterRevert'] || isset($this->technicalEvents[$event['id']])))
                || $event['pairActionId'] === null || (int)$event['pairActionId'] !== (int)$start['id']
                || $start['time'] > $event['time']
                || ($action === Action::RETURN && (int)$start['userId'] !== (int)$event['userId'])
            ) {
                continue;
            }
            if ($start['pairActionId'] !== null) {
                $oldReturn = $this->db->query(
                    'SELECT id, bikeNum, action, time FROM history WHERE id = :id',
                    ['id' => $start['pairActionId']],
                )->fetchAssoc();
                if (
                    $oldReturn === null || (int)$oldReturn['bikeNum'] !== $bikeId
                    || !in_array($oldReturn['action'], [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                    || $oldReturn['time'] > $start['time']
                    || ((int)$oldReturn['id'] >= (int)$start['id'] && $oldReturn['time'] >= $start['time'])
                ) {
                    continue;
                }
            }
            $ids = [(int)$start['id'], (int)$event['id']];
            if ($this->hasOtherReferences($reversed ? $ids : [(int)$start['id']], $ids)) {
                continue;
            }
            if ($start['pairActionId'] !== null) {
                $starts[(int)$start['id']] = $start;
            }
            if ($reversed) {
                // Preserve the explicit pair when insertion order differs from the recorded trip times.
                $this->reorderedPairs[(int)$start['id']] = true;
                $this->reorderedPairs[(int)$event['id']] = true;
            }
        }

        return $starts;
    }

    private function findChainStarts(?int $bikeNumber): array
    {
        $previous = [];
        $beforePrevious = [];
        $starts = [];
        foreach ($this->iterateEvents($bikeNumber) as $event) {
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $bikeId = (int)$event['bikeNum'];
            $return = $previous[$bikeId] ?? null;
            $rent = $beforePrevious[$bikeId] ?? null;
            $event['afterRevert'] = ($return['action'] ?? null) === Action::REVERT->value;
            if (
                in_array($action, [Action::RENT, Action::FORCE_RENT], true)
                && $event['pairActionId'] !== null && (int)$event['userId'] > 0
                && in_array($return['action'] ?? null, [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                && in_array($rent['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                && (int)$event['pairActionId'] === (int)$return['id']
                && $return['pairActionId'] !== null && (int)$return['pairActionId'] === (int)$rent['id']
                && (int)$rent['userId'] > 0 && !$rent['afterRevert']
                && $rent['time'] <= $return['time'] && $return['time'] <= $event['time']
                && ($return['action'] === Action::FORCE_RETURN->value || $return['userId'] === $rent['userId'])
                && !$this->hasOtherReferences(
                    [(int)$rent['id'], (int)$return['id']],
                    [(int)$rent['id'], (int)$return['id'], (int)$event['id']],
                )
            ) {
                $starts[(int)$event['id']] = $event;
            }
            $beforePrevious[$bikeId] = $return;
            $previous[$bikeId] = $event;
        }

        return $starts;
    }

    private function invalidPairReason(?array $start, array $return): ?string
    {
        if (($start['afterRevert'] ?? false) || ($start['action'] ?? null) === Action::REVERT->value) {
            return 'return_after_revert';
        }
        if ($start === null || !in_array($start['action'], [Action::RENT->value, Action::FORCE_RENT->value], true)) {
            return 'no_adjacent_start';
        }
        if ((int)$start['userId'] <= 0) {
            return 'unknown_holder';
        }
        if ($return['time'] < $start['time']) {
            return 'time_goes_backwards';
        }
        if ($return['action'] === Action::RETURN->value && (int)$start['userId'] !== (int)$return['userId']) {
            return 'different_holder';
        }
        if (
            ($start['pairActionId'] !== null && (int)$start['pairActionId'] !== (int)$return['id'])
            || ($return['pairActionId'] !== null && (int)$return['pairActionId'] !== (int)$start['id'])
        ) {
            return 'conflicting_pair';
        }
        $pairIds = [(int)$start['id'], (int)$return['id']];
        if ($this->hasOtherReferences($pairIds, $pairIds)) {
            return 'other_references';
        }

        return null;
    }

    private function skip(array $event, string $reason, array &$skipped, OutputInterface $output): void
    {
        $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
        $output->writeln(
            sprintf('Skip history %d (bike %d): %s', $event['id'], $event['bikeNum'], $reason),
            OutputInterface::VERBOSITY_VERBOSE,
        );
    }

    private function iterateEvents(?int $bikeNumber): iterable
    {
        $afterId = 0;
        do {
            $params = ['afterId' => $afterId];
            $bikeFilter = '';
            if ($bikeNumber !== null) {
                $bikeFilter = ' AND bikeNum = :bikeNum';
                $params['bikeNum'] = $bikeNumber;
            }
            $rows = $this->db->query(
                'SELECT id, bikeNum, userId, action, time, pairActionId, parameter FROM history
                 WHERE id > :afterId' . $bikeFilter . ' ORDER BY id LIMIT 1000',
                $params,
            )->fetchAllAssoc();
            foreach ($rows as $row) {
                $afterId = (int)$row['id'];
                if (isset($this->reviewedDeletions[$row['id']]) || isset($this->historicalEndIds[$row['id']])) {
                    continue;
                }
                if (isset($this->legacyForcedReturns[$row['id']])) {
                    $row['action'] = Action::FORCE_RETURN->value;
                }
                if (isset($this->reassignedReturns[$row['id']])) {
                    $row['pairActionId'] = $this->reassignedReturns[$row['id']]['rentId'];
                }
                if (isset($this->revertPairs[$row['id']])) {
                    $row['pairActionId'] = $this->revertPairs[$row['id']]['rentId'];
                }
                if (
                    isset($this->repeatedReturns[$row['id']]) || isset($this->chainStarts[$row['id']])
                    || isset($this->technicalLinks[$row['id']])
                ) {
                    $row['pairActionId'] = null;
                }
                yield $row;
            }
        } while (count($rows) === 1000);
    }

    /**
     * @param list<int> $targetIds
     * @param list<int> $allowedIds
     */
    private function hasOtherReferences(array $targetIds, array $allowedIds): bool
    {
        // Include references from other bikes, even when the scan is filtered.
        $placeholders = implode(', ', array_fill(0, count($targetIds), '?'));
        $references = $this->db->query(
            'SELECT id, pairActionId FROM history WHERE pairActionId IN (' . $placeholders . ')',
            $targetIds,
        )->fetchAllAssoc();
        foreach ($this->reassignedReturns + $this->revertPairs as $id => $correction) {
            if (in_array($correction['rentId'], $targetIds, true)) {
                $references[] = ['id' => $id, 'pairActionId' => $correction['rentId']];
            }
        }
        foreach ($references as $reference) {
            // Preview must ignore exactly the links that applying would remove.
            $id = (int)$reference['id'];
            $targetId = $this->reassignedReturns[$id]['rentId']
                ?? $this->revertPairs[$id]['rentId'] ?? (int)$reference['pairActionId'];
            if (!in_array($targetId, $targetIds, true)) {
                continue;
            }
            if (
                !in_array($id, $allowedIds, true)
                && !isset($this->chainStarts[$id]) && !isset($this->repeatedReturns[$id])
                && !isset($this->technicalLinks[$id])
            ) {
                return true;
            }
        }

        return false;
    }

    private function updatePair(array $event, ?int $pairActionId): void
    {
        $affected = $this->db->query(
            'UPDATE history SET pairActionId = :newPair
             WHERE id = :id AND bikeNum = :bikeNum AND userId = :userId AND action = :action
               AND time = :time AND parameter = :parameter AND pairActionId <=> :oldPair',
            [
                'newPair' => $pairActionId,
                'id' => $event['id'],
                'bikeNum' => $event['bikeNum'],
                'userId' => $event['userId'],
                'action' => $event['action'],
                'time' => $event['time'],
                'parameter' => $event['parameter'],
                'oldPair' => $event['pairActionId'],
            ],
        )->rowCount();
        if ($affected !== 1) {
            throw new \RuntimeException(
                sprintf('History %d changed during migration; rerun the preview.', $event['id']),
            );
        }
    }
}
