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
    private array $chainStarts = [];
    private array $repeatedReturns = [];
    private array $reassignedReturns = [];

    public function __construct(private readonly DbInterface $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('bike', null, InputOption::VALUE_REQUIRED, 'Process only this bike number')
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
        $this->chainStarts = [];
        $this->repeatedReturns = [];
        $this->reassignedReturns = [];
        $this->repeatedReturns = $this->findRepeatedReturnLinks($bikeNumber);
        $this->chainStarts = $this->findStartsWithOwnPair($bikeNumber);
        $this->reassignedReturns = $this->findMislinkedReturns($bikeNumber);
        $this->repeatedReturns += $this->findRepeatedReturnLinks($bikeNumber);
        $this->chainStarts += $this->findStartsWithOwnPair($bikeNumber);
        $this->chainStarts += $this->findChainStarts($bikeNumber);
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
        $unchanged = 0;
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
            $isFirstEvent = !array_key_exists($bikeId, $previous);
            $start = $previous[$bikeId] ?? null;
            $previous[$bikeId] = $event;
            if ($action === Action::REVERT || $action === null) {
                if ($event['action'] === '' && $bikeId === 0 && (int)$event['userId'] === 0) {
                    ++$emptyServiceEvents;
                    $output->writeln(
                        sprintf('Ignore history %d: empty action without bike or user', $event['id']),
                        OutputInterface::VERBOSITY_VERBOSE,
                    );
                } else {
                    $this->skip($event, $action === Action::REVERT ? 'revert' : 'unknown_action', $skipped, $output);
                }
                continue;
            }
            if (in_array($action, [Action::RENT, Action::FORCE_RENT], true)) {
                if (
                    in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                    && !($start['afterRevert'] ?? false)
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
            ) {
                $this->skip($event, 'no_return', $skipped, $output);
            }
        }

        $io->table(['Result', 'Count'], [
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
            if (
                $bikeId <= 0 || !in_array($action, [Action::RETURN, Action::FORCE_RETURN], true)
                || !in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)
                || $start['afterRevert'] || (int)$start['userId'] <= 0 || $start['pairActionId'] === null
                || $event['pairActionId'] === null || (int)$event['pairActionId'] !== (int)$start['id']
                || $start['time'] > $event['time']
                || ($action === Action::RETURN && (int)$start['userId'] !== (int)$event['userId'])
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
                && !$this->hasOtherReferences([(int)$start['id']], [(int)$event['id']])
            ) {
                $starts[(int)$start['id']] = $start;
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
                'SELECT id, bikeNum, userId, action, time, pairActionId FROM history
                 WHERE id > :afterId' . $bikeFilter . ' ORDER BY id LIMIT 1000',
                $params,
            )->fetchAllAssoc();
            foreach ($rows as $row) {
                $afterId = (int)$row['id'];
                if (isset($this->reassignedReturns[$row['id']])) {
                    $row['pairActionId'] = $this->reassignedReturns[$row['id']]['rentId'];
                }
                if (isset($this->repeatedReturns[$row['id']]) || isset($this->chainStarts[$row['id']])) {
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
        foreach ($this->reassignedReturns as $id => $correction) {
            if (in_array($correction['rentId'], $targetIds, true)) {
                $references[] = ['id' => $id, 'pairActionId' => $correction['rentId']];
            }
        }
        foreach ($references as $reference) {
            // Preview must ignore exactly the links that applying would remove.
            $id = (int)$reference['id'];
            $targetId = $this->reassignedReturns[$id]['rentId'] ?? (int)$reference['pairActionId'];
            if (!in_array($targetId, $targetIds, true)) {
                continue;
            }
            if (
                !in_array($id, $allowedIds, true)
                && !isset($this->chainStarts[$id]) && !isset($this->repeatedReturns[$id])
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
               AND time = :time AND pairActionId <=> :oldPair',
            [
                'newPair' => $pairActionId,
                'id' => $event['id'],
                'bikeNum' => $event['bikeNum'],
                'userId' => $event['userId'],
                'action' => $event['action'],
                'time' => $event['time'],
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
