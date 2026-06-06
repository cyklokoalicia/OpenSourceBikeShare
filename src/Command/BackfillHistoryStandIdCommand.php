<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Spec 0013: one-off backfill of history.standId for rows written before the column was
 * populated (the gap opened mid-2016 and persisted into the Symfony rewrite). After this
 * runs, every stand-bearing history row carries its stand id so findStandHistory can use
 * the exact, index-backed filter for historical data too.
 *
 *   - RETURN / FORCERETURN: the stand id is the (numeric) parameter — set directly.
 *   - REVERT: parameter is "{standId}|{code}" — take the part before the pipe.
 *   - RENT / FORCERENT: parameter is the lock code, not a stand. The origin stand is the
 *     bike's immediately-preceding RETURN/FORCERETURN parameter — the same inference the
 *     0009 query used to do at read time, now resolved once and stored.
 */
#[AsCommand(
    name: 'app:backfill_history_standid',
    description: 'Backfill history.standId for legacy rows (RENT origin / RETURN+REVERT destination)'
)]
class BackfillHistoryStandIdCommand extends Command
{
    public function __construct(
        private readonly DbInterface $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing')
            ->addOption(
                'since',
                null,
                InputOption::VALUE_REQUIRED,
                'Only process rows with time >= this datetime',
                '2016-01-01 00:00:00'
            )
            ->addOption('batch', 'b', InputOption::VALUE_REQUIRED, 'RENT rows processed per batch', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $since = (string)$input->getOption('since');
        $batch = max(1, (int)$input->getOption('batch'));

        $io->title('Backfill history.standId');
        $io->text(sprintf('Since: %s%s', $since, $dryRun ? '  (dry-run — no writes)' : ''));

        $returns = $this->backfillReturns($since, $dryRun);
        $reverts = $this->backfillReverts($since, $dryRun);
        [$rentsResolved, $rentsUnresolved] = $this->backfillRents($since, $batch, $dryRun, $io);

        $io->section('Summary');
        $io->table(
            ['Action group', $dryRun ? 'Would set' : 'Updated'],
            [
                ['RETURN / FORCERETURN (from parameter)', $returns],
                ['REVERT (from "standId|code")', $reverts],
                ['RENT / FORCERENT (origin inferred)', $rentsResolved],
                ['RENT / FORCERENT (no prior return — left NULL)', $rentsUnresolved],
            ]
        );

        if ($dryRun) {
            $io->warning('Dry run complete. Re-run without --dry-run to apply.');
        } else {
            $io->success('Backfill complete.');
        }

        return Command::SUCCESS;
    }

    private function backfillReturns(string $since, bool $dryRun): int
    {
        $where = "action IN (:return, :forceReturn)
                  AND standId IS NULL
                  AND parameter REGEXP '^[0-9]+$'
                  AND time >= :since";
        $params = [
            'return' => Action::RETURN->value,
            'forceReturn' => Action::FORCE_RETURN->value,
            'since' => $since,
        ];

        if ($dryRun) {
            return $this->countWhere($where, $params);
        }

        return $this->db->query(
            'UPDATE history SET standId = CAST(parameter AS UNSIGNED) WHERE ' . $where,
            $params
        )->rowCount();
    }

    private function backfillReverts(string $since, bool $dryRun): int
    {
        $where = "action = :revert
                  AND standId IS NULL
                  AND parameter REGEXP '^[0-9]+\\\\|'
                  AND time >= :since";
        $params = [
            'revert' => Action::REVERT->value,
            'since' => $since,
        ];

        if ($dryRun) {
            return $this->countWhere($where, $params);
        }

        return $this->db->query(
            "UPDATE history SET standId = CAST(SUBSTRING_INDEX(parameter, '|', 1) AS UNSIGNED) WHERE " . $where,
            $params
        )->rowCount();
    }

    /**
     * @return array{0: int, 1: int} resolved (origin found), unresolved (no prior return)
     */
    private function backfillRents(string $since, int $batch, bool $dryRun, SymfonyStyle $io): array
    {
        $resolved = 0;
        $unresolved = 0;
        $lastId = 0;

        while (true) {
            $rows = $this->db->query(
                "SELECT id, bikeNum, time
                 FROM history
                 WHERE action IN (:rent, :forceRent)
                   AND standId IS NULL
                   AND time >= :since
                   AND id > :lastId
                 ORDER BY id ASC
                 LIMIT :batch",
                [
                    'rent' => Action::RENT->value,
                    'forceRent' => Action::FORCE_RENT->value,
                    'since' => $since,
                    'lastId' => $lastId,
                    'batch' => $batch,
                ]
            )->fetchAllAssoc();

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int)$row['id'];
                $origin = $this->findOriginStand((int)$row['bikeNum'], (string)$row['time'], $lastId);

                if ($origin === null) {
                    $unresolved++;
                    continue;
                }

                if (!$dryRun) {
                    $this->db->query(
                        'UPDATE history SET standId = :standId WHERE id = :id',
                        ['standId' => $origin, 'id' => $lastId]
                    );
                }

                $resolved++;
            }

            $io->writeln(sprintf(
                '  …processed up to id %d (resolved %d, unresolved %d)',
                $lastId,
                $resolved,
                $unresolved
            ));
        }

        return [$resolved, $unresolved];
    }

    /**
     * The stand the bike was last returned to before $time/$id — its origin for that rent.
     */
    private function findOriginStand(int $bikeNum, string $time, int $rentId): ?int
    {
        $row = $this->db->query(
            "SELECT CAST(parameter AS UNSIGNED) AS standId
             FROM history
             WHERE bikeNum = :bikeNum
               AND action IN (:return, :forceReturn)
               AND parameter REGEXP '^[0-9]+$'
               AND (time < :timeBefore OR (time = :timeEqual AND id < :rentId))
             ORDER BY time DESC, id DESC
             LIMIT 1",
            [
                'bikeNum' => $bikeNum,
                'return' => Action::RETURN->value,
                'forceReturn' => Action::FORCE_RETURN->value,
                'timeBefore' => $time,
                'timeEqual' => $time,
                'rentId' => $rentId,
            ]
        )->fetchAssoc();

        return $row ? (int)$row['standId'] : null;
    }

    private function countWhere(string $where, array $params): int
    {
        $row = $this->db->query('SELECT COUNT(*) AS c FROM history WHERE ' . $where, $params)->fetchAssoc();

        return (int)($row['c'] ?? 0);
    }
}
