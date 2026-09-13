<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\Enum\Action;
use BikeShare\Repository\RentalHistoryBackfillRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backfill_rental_pairs', description: 'Fill and normalize unambiguous historical rental pairs')]
class BackfillRentalPairsCommand extends Command
{
    public function __construct(private readonly RentalHistoryBackfillRepository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('to-id', null, InputOption::VALUE_REQUIRED, 'Required inclusive historical history.id boundary')
            ->addOption('bike', null, InputOption::VALUE_REQUIRED, 'Process only this bike number')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write changes; otherwise only preview them')
            ->setHelp('Use the same --to-id for preview and --apply. Add -v for event IDs and skip reasons.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $toId = filter_var($input->getOption('to-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $bike = $input->getOption('bike');
        $bikeNumber = $bike === null ? null : filter_var($bike, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($toId === false || $bikeNumber === false) {
            $io->error('--to-id is required; --to-id and --bike must be positive integers.');

            return Command::INVALID;
        }

        $apply = (bool)$input->getOption('apply');
        $io->writeln(sprintf('%s history through ID %d.', $apply ? 'Applying' : 'Previewing', $toId));
        $previous = [];
        $changed = 0;
        $unchanged = 0;
        $skipped = [];
        foreach ($this->repository->iterateEvents($toId, $bikeNumber) as $event) {
            $bikeId = (int)$event['bikeNum'];
            $action = Action::tryFrom($event['action']);
            if (
                $action !== null && !in_array($action, [
                    Action::RENT, Action::FORCE_RENT, Action::RETURN, Action::FORCE_RETURN, Action::REVERT,
                ], true)
            ) {
                continue;
            }
            $start = $previous[$bikeId] ?? null;
            $previous[$bikeId] = $event;
            if ($action === Action::REVERT || $action === null) {
                $this->skip($event, 'revert_or_unknown_action', $skipped, $output);
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
                    $this->repository->updatePair($event, (int)$start['id']);
                }
                if ($start['pairActionId'] !== null) {
                    $this->repository->updatePair($start, null);
                }
            }
            ++$changed;
        }

        foreach ($previous as $event) {
            if (in_array($event['action'] ?? null, [Action::RENT->value, Action::FORCE_RENT->value], true)) {
                $this->skip($event, 'no_return_in_range', $skipped, $output);
            }
        }

        $io->table(['Result', 'Count'], [
            [$apply ? 'Pairs updated' : 'Pairs to update', $changed],
            ['Already linked', $unchanged],
            ['Skipped returns/events', array_sum($skipped)],
        ]);
        foreach ($skipped as $reason => $count) {
            $io->writeln(sprintf('%s: %d', $reason, $count));
        }

        return Command::SUCCESS;
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
        if ($this->repository->hasOtherReferences((int)$start['id'], (int)$return['id'])) {
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
}
