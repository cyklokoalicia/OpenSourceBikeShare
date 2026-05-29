<?php

declare(strict_types=1);

namespace BikeShare\Command;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
use BikeShare\Enum\CreditChangeType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate_credit_history',
    description: 'Migrate legacy credit history records to new JSON format'
)]
class MigrateCreditHistoryCommand extends Command
{
    public function __construct(
        private readonly DbInterface $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without modifying database')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of users to migrate', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $limit = (int)$input->getOption('limit');

        $io->title('Credit History Migration');

        if ($dryRun) {
            $io->note('Running in dry-run mode. No changes will be made.');
        }

        // Get users with legacy records
        $usersWithLegacy = $this->findUsersWithLegacyRecords($limit);

        if (empty($usersWithLegacy)) {
            $io->success('No users with legacy records found to migrate.');

            return Command::SUCCESS;
        }

        $io->info("Found " . count($usersWithLegacy) . " users with legacy records to migrate.");

        $totalMigrated = 0;
        $totalErrors = 0;
        $totalDeleted = 0;
        $balanceMismatches = [];

        $io->progressStart(count($usersWithLegacy));

        foreach ($usersWithLegacy as $userRow) {
            $userId = (int)$userRow['userId'];

            try {
                $result = $this->migrateUserRecords($userId, $dryRun);
                $totalMigrated += $result['migrated'];
                $totalDeleted += $result['deleted'];

                // Validate balance
                if ($result['balance_mismatch'] !== null) {
                    $balanceMismatches[] = [
                        'userId' => $userId,
                        'current' => $result['current_balance'],
                        'calculated' => $result['calculated_balance'],
                        'difference' => $result['balance_mismatch'],
                    ];

                    // Add adjustment record to correct the balance
                    if (!$dryRun) {
                        $this->addBalanceAdjustmentRecord(
                            $userId,
                            $result['balance_mismatch'],
                            $result['current_balance'],
                            $result['oldest_time']
                        );
                    }
                }
            } catch (\Exception $e) {
                $totalErrors++;
                $io->warning(sprintf('Error migrating user %d: %s', $userId, $e->getMessage()));
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->newLine();
        $io->section('Migration Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Users processed', count($usersWithLegacy)],
                ['Records migrated', $totalMigrated],
                ['Records deleted (orphans/zero amount)', $totalDeleted],
                ['Balance mismatches', count($balanceMismatches)],
                ['Errors', $totalErrors],
            ]
        );

        // Show balance mismatches
        if (!empty($balanceMismatches)) {
            $io->section('Balance Mismatches');
            $io->warning('The following users have balance discrepancies (history sum ≠ current balance):');
            $io->table(
                ['User ID', 'Current Balance', 'Calculated from History', 'Difference'],
                array_map(
                    fn($m) => [$m['userId'], $m['current'], $m['calculated'], $m['difference']],
                    $balanceMismatches
                )
            );
        }

        if ($dryRun) {
            $io->warning('Dry run complete. Run without --dry-run to apply changes.');
        } else {
            $io->success('Migration completed successfully.');
        }

        return Command::SUCCESS;
    }

    private function findUsersWithLegacyRecords(int $limit): array
    {
        $result = $this->db->query(
            "SELECT DISTINCT userId 
             FROM history 
             WHERE action = :action 
             AND parameter NOT LIKE '{%'
             ORDER BY userId ASC
             LIMIT :limit",
            [
                'action' => Action::CREDIT_CHANGE->value,
                'limit' => $limit,
            ]
        );

        return $result->fetchAllAssoc();
    }

    private function migrateUserRecords(int $userId, bool $dryRun): array
    {
        // Get current user credit balance
        $creditResult = $this->db->query(
            "SELECT credit FROM credit WHERE userId = :userId",
            ['userId' => $userId]
        );
        $creditRow = $creditResult->fetchAssoc();
        $currentBalance = $creditRow ? (float)$creditRow['credit'] : 0.0;

        // Get ALL user's credit history records ordered by time DESC (newest first)
        $historyResult = $this->db->query(
            "SELECT id, action, parameter, time, bikeNum 
             FROM history 
             WHERE userId = :userId 
             AND action IN (:creditChange, :credit)
             ORDER BY time DESC, id DESC",
            [
                'userId' => $userId,
                'creditChange' => Action::CREDIT_CHANGE->value,
                'credit' => Action::CREDIT->value,
            ]
        );

        $records = $historyResult->fetchAllAssoc();

        if (empty($records)) {
            $difference = abs($currentBalance);
            $balanceMismatch = ($difference > 0.01) ? round($currentBalance, 2) : null;

            return [
                'migrated' => 0,
                'deleted' => 0,
                'balance_mismatch' => $balanceMismatch,
                'current_balance' => $currentBalance,
                'calculated_balance' => 0.0,
                'oldest_time' => null,
            ];
        }

        $migrated = 0;
        $deleted = 0;
        $runningBalance = $currentBalance;
        $totalHistorySum = 0.0;
        $oldestTime = null;

        // Process records from newest to oldest
        foreach ($records as $record) {
            $action = $record['action'];

            // Delete orphan CREDIT (balance-only) records
            if ($action === Action::CREDIT->value) {
                if (!$dryRun) {
                    $this->db->query(
                        "DELETE FROM history WHERE id = :id",
                        ['id' => $record['id']]
                    );
                }

                $deleted++;
                continue;
            }

            // Skip already migrated JSON records
            $parameter = $record['parameter'];
            if (str_starts_with($parameter, '{')) {
                // This is already JSON - extract amount to adjust running balance
                $jsonData = json_decode($parameter, true);
                if (isset($jsonData['amount'])) {
                    $amount = (float)$jsonData['amount'];
                    $totalHistorySum += $amount;
                    // Reverse the transaction to get previous balance
                    $runningBalance -= $amount;
                }

                continue;
            }

            // Parse legacy record
            $convertedRecords = $this->convertRecord($parameter);

            if (empty($convertedRecords)) {
                if (!$dryRun) {
                    $this->db->query(
                        "DELETE FROM history WHERE id = :id",
                        ['id' => $record['id']]
                    );
                }

                $deleted++;
                continue;
            }

            // Calculate balances working backwards
            // Records are processed newest to oldest, so we need to calculate backwards
            $recordsWithBalance = [];
            foreach (array_reverse($convertedRecords) as $converted) {
                // Current runningBalance is the balance AFTER this record
                $converted['balance'] = $runningBalance;
                $recordsWithBalance[] = $converted;
                // Move to balance before this transaction and track sum
                $amount = (float)$converted['amount'];
                $totalHistorySum += $amount;
                $runningBalance -= $amount;
            }

            // Reverse back to original order (oldest to newest within this batch)
            $recordsWithBalance = array_reverse($recordsWithBalance);
            $oldestTime = $record['time'];

            if (!$dryRun) {
                $this->replaceRecord(
                    (int)$record['id'],
                    $recordsWithBalance,
                    [
                        'userId' => $userId,
                        'bikeNum' => $record['bikeNum'],
                        'time' => $record['time'],
                    ]
                );
            }

            $migrated++;
        }

        // Validate: starting balance (runningBalance) should be close to 0
        // OR: sum of all history amounts should equal current balance
        $calculatedBalance = $totalHistorySum;
        $difference = abs($currentBalance - $calculatedBalance);
        $balanceMismatch = null;

        // Allow small floating point tolerance (0.01)
        if ($difference > 0.01) {
            $balanceMismatch = round($currentBalance - $calculatedBalance, 2);
        }

        return [
            'migrated' => $migrated,
            'deleted' => $deleted,
            'balance_mismatch' => $balanceMismatch,
            'current_balance' => $currentBalance,
            'calculated_balance' => round($calculatedBalance, 2),
            'oldest_time' => $oldestTime,
        ];
    }

    private function convertRecord(string $parameter): array
    {
        $parts = explode('|', $parameter, 2);
        $totalAmount = (float)$parts[0];

        if (abs($totalAmount) < 0.001) {
            return [];
        }

        $breakdown = $parts[1] ?? '';
        $newRecords = [];

        // Handle add+ format (credit additions)
        if (str_starts_with($breakdown, 'add+')) {
            $addParts = explode('|', $breakdown);
            $type = CreditChangeType::CREDIT_ADD;

            if (isset($addParts[1]) && $addParts[1] === 'longstandbonus') {
                $type = CreditChangeType::LONG_STAND_BONUS;
            }

            $jsonData = [
                'amount' => $totalAmount,
                'balance' => 0.0, // Will be calculated later
                'reason' => $type->value,
            ];

            // Coupon code
            if (isset($addParts[1]) && $addParts[1] !== 'longstandbonus') {
                $jsonData['couponCode'] = $addParts[1];
            }

            $newRecords[] = $jsonData;

            return $newRecords;
        }

        // Parse component breakdown: "overfree-1;flat-72;"
        $components = explode(';', rtrim($breakdown, ';'));

        foreach ($components as $component) {
            if (empty($component)) {
                continue;
            }

            if (preg_match('/^(\w+)([-+])(\d+(?:\.\d+)?)$/', $component, $matches)) {
                $compType = $matches[1];
                $sign = $matches[2];
                $value = (float)$matches[3];

                if (abs($value) < 0.001) {
                    continue;
                }

                $signedAmount = $sign === '-' ? -$value : $value;

                $reason = match ($compType) {
                    'overfree' => CreditChangeType::OVER_FREE_TIME,
                    'flat' => CreditChangeType::FLAT_RATE,
                    'long', 'longrent' => CreditChangeType::LONG_RENTAL,
                    'rerent' => CreditChangeType::RERENT_PENALTY,
                    'double' => CreditChangeType::DOUBLE_PRICE,
                    'longstandbonus' => CreditChangeType::LONG_STAND_BONUS,
                    default => null,
                };

                if ($reason === null) {
                    continue;
                }

                $newRecords[] = [
                    'amount' => $signedAmount,
                    'balance' => 0.0, // Will be calculated later
                    'reason' => $reason->value,
                ];
            }
        }

        // Fallback if nothing parsed
        if ($newRecords === [] && abs($totalAmount) >= 0.001) {
            $newRecords[] = [
                'amount' => $totalAmount,
                'balance' => 0.0,
                'reason' => $totalAmount >= 0 ? CreditChangeType::CREDIT_ADD->value : 'unknown',
            ];
        }

        return $newRecords;
    }

    private function replaceRecord(int $originalId, array $newRecords, array $original): void
    {
        // Update first record in place
        $firstRecord = array_shift($newRecords);
        $this->db->query(
            "UPDATE history SET parameter = :parameter WHERE id = :id",
            [
                'id' => $originalId,
                'parameter' => json_encode($firstRecord, JSON_THROW_ON_ERROR),
            ]
        );

        // Insert additional records
        foreach ($newRecords as $record) {
            $this->db->query(
                "INSERT INTO history (userId, bikeNum, action, parameter, time) 
                 VALUES (:userId, :bikeNum, :action, :parameter, :time)",
                [
                    'userId' => $original['userId'],
                    'bikeNum' => $original['bikeNum'],
                    'action' => Action::CREDIT_CHANGE->value,
                    'parameter' => json_encode($record, JSON_THROW_ON_ERROR),
                    'time' => $original['time'],
                ]
            );
        }
    }

    private function addBalanceAdjustmentRecord(
        int $userId,
        float $difference,
        float $finalBalance,
        ?string $oldestTime
    ): void {
        $parameter = json_encode([
            'amount' => $difference,
            'balance' => $finalBalance,
            'reason' => CreditChangeType::BALANCE_ADJUSTMENT->value,
            'context' => 'Migration balance alignment to match current credit',
        ], JSON_THROW_ON_ERROR);

        $time = $oldestTime ? new \DateTimeImmutable($oldestTime) : new \DateTimeImmutable();
        if ($oldestTime) {
            $time = $time->modify('-5 seconds');
        }

        $this->db->query(
            "INSERT INTO history (userId, bikeNum, action, parameter, time) 
             VALUES (:userId, :bikeNum, :action, :parameter, :time)",
            [
                'userId' => $userId,
                'bikeNum' => 0,
                'action' => Action::CREDIT_CHANGE->value,
                'parameter' => $parameter,
                'time' => $time->format('Y-m-d H:i:s'),
            ]
        );
    }
}
