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
        $this->repeatedReturns = $this->findRepeatedReturnLinks($bikeNumber);
        foreach ($this->repeatedReturns as $return) {
            $output->writeln(
                sprintf('Clear repeated return %d -> rent %d', $return['id'], $return['pairActionId']),
                OutputInterface::VERBOSITY_VERBOSE,
            );
            if ($apply) {
                $this->updatePair($return, null);
            }
        }
        $this->chainStarts = $this->findChainStarts($bikeNumber);
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
            if (isset($this->chainStarts[$event['id']])) {
                $event['pairActionId'] = null;
            }
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
                if (in_array($start['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)) {
                    $this->skip($start, 'superseded_start', $skipped, $output);
                }
                // Legacy REVERT writes an extra RENT/RETURN. Do not infer a trip from that pair.
                if (($start['action'] ?? null) === Action::REVERT->value) {
                    $previous[$bikeId] = null;
                    $this->skip($event, 'start_after_revert', $skipped, $output);
                }
                continue;
            }
            $reason = $this->invalidPairReason($start, $event);
            if ($reason === 'no_adjacent_start' && $action === Action::RETURN && $bikeId > 0) {
                if ($isFirstEvent) {
                    $reason = 'initial_return';
                } elseif (
                    in_array($start['action'] ?? null, [Action::RETURN->value, Action::FORCE_RETURN->value], true)
                ) {
                    $reason = 'return_after_return';
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
            if (in_array($event['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)) {
                $this->skip($event, 'no_return', $skipped, $output);
            }
        }

        $io->table(['Result', 'Count'], [
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
                // Only an existing adjacent closing link can prove a subsequent RETURN is redundant.
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
                $action === Action::RETURN && $closing !== null
                && $event['pairActionId'] !== null
                && (int)$event['pairActionId'] === (int)$closing['pairActionId']
                && $closing['time'] <= $event['time']
            ) {
                $repeated[(int)$event['id']] = $event;
                $closed[$bikeId] = $event;
            } else {
                unset($closed[$bikeId]);
            }
        }

        return $repeated;
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
                && !$this->hasOtherReferences((int)$rent['id'], (int)$return['id'], (int)$event['id'])
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
        if ($this->hasOtherReferences((int)$start['id'], (int)$return['id'])) {
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
                if (isset($this->repeatedReturns[$row['id']])) {
                    $row['pairActionId'] = null;
                }
                yield $row;
            }
        } while (count($rows) === 1000);
    }

    private function hasOtherReferences(int $startId, int $returnId, ?int $nextStartId = null): bool
    {
        // Include references from other bikes, even when the scan is filtered.
        $references = $this->db->query(
            'SELECT id FROM history WHERE pairActionId IN (:startId, :returnId)
             AND id NOT IN (:excludeStartId, :excludeReturnId, :excludeNextStartId)',
            [
                'startId' => $startId,
                'returnId' => $returnId,
                'excludeStartId' => $startId,
                'excludeReturnId' => $returnId,
                'excludeNextStartId' => $nextStartId ?? $startId,
            ],
        )->fetchAllAssoc();
        foreach ($references as $reference) {
            // Preview must ignore exactly the chain links that applying would remove.
            if (!isset($this->chainStarts[$reference['id']]) && !isset($this->repeatedReturns[$reference['id']])) {
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
