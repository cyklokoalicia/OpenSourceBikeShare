<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Rent;

use BikeShare\Db\DbInterface;
use BikeShare\Rent\DTO\RentalWriteResult;
use BikeShare\Rent\Exception\RentalLedgerConflict;
use BikeShare\Rent\NormalRentalPlanner;
use BikeShare\Rent\NormalRentalWriter;
use BikeShare\Repository\RentalLedgerRepository;
use BikeShare\Test\Fixtures\FailingLedgerDb;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

class NormalRentalWriterTest extends KernelTestCase
{
    private const USER = 991301;
    private const OTHER_USER = 991302;
    private const BIKE = 99131;
    private const OTHER_BIKE = 99132;

    private DbInterface $db;
    private MockClock $clock;
    private NormalRentalWriter $writer;
    /** @var resource|null */
    private $worker = null;
    private array $pipes = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = self::getContainer()->get(DbInterface::class);
        $this->clock = new MockClock('2026-09-09 12:00:00');
        $this->writer = $this->makeWriter($this->db);
        $this->db->exec("INSERT INTO users (userId, userName, password, mail, number, userLimit) VALUES
            (991301,'Ledger test','','ledger1@example.invalid','991301',1),
            (991302,'Ledger test','','ledger2@example.invalid','991302',1)");
        $this->db->exec('INSERT INTO bikes VALUES (99131,NULL,1,1234), (99132,NULL,1,1234)');
        $this->db->exec('INSERT INTO credit (userId, credit) VALUES (991301,100)');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->worker)) {
            proc_terminate($this->worker);
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->worker);
            $this->worker = null;
        }
        $this->db->exec('DELETE FROM history WHERE bikeNum IN (99131,99132) AND pairActionId IS NOT NULL');
        $this->db->exec('DELETE FROM history WHERE bikeNum IN (99131,99132) OR userId IN (991301,991302)');
        $this->db->exec('DELETE FROM bikes WHERE bikeNum IN (99131,99132)');
        $this->db->exec('DELETE FROM credit WHERE userId IN (991301,991302)');
        $this->db->exec('DELETE FROM users WHERE userId IN (991301,991302)');
        parent::tearDown();
    }

    public function testRentAndReturnHaveOneDirectionalPairAndObservedStands(): void
    {
        $rent = $this->writer->rent(self::USER, self::BIKE, '0042');
        $startBeforeReturn = $this->history()[0];
        $this->clock->sleep(3600);
        $return = $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId);
        $rows = $this->history();
        self::assertSame($startBeforeReturn, $rows[0], 'Closing must not modify the start');
        self::assertSame(['RENT', 'RETURN'], array_column($rows, 'action'));
        self::assertSame([null, $rent->rentId], array_column($rows, 'pairActionId'));
        self::assertSame([1, 2], array_column($rows, 'standId'));
        self::assertSame(['0042', '2'], array_column($rows, 'parameter'));
        self::assertSame($rent->rentId, $return->rentId);
        self::assertSame($rows[1]['id'], $return->eventId);
        self::assertSame($rent->transition->startedAt->getTimestamp(), $return->transition->startedAt->getTimestamp());
        self::assertNull($this->bike()['currentUser']);
        self::assertSame(2, $this->bike()['currentStand']);
        self::assertSame(42, $this->bike()['currentCode']);
    }

    public function testSameTimestampCyclesRemainDistinctAndStaleReturnCannotCloseTheNextRent(): void
    {
        $first = $this->writer->rent(self::USER, self::BIKE, '2345');
        $this->writer->returnBike(self::USER, self::BIKE, 2, $first->rentId);
        $second = $this->writer->rent(self::USER, self::BIKE, '3456');
        $this->assertConflict('rental_ledger_stale_rental', fn() =>
            $this->writer->returnBike(self::USER, self::BIKE, 1, $first->rentId));
        self::assertCount(3, $this->history());
        self::assertSame(self::USER, $this->bike()['currentUser']);
        $this->writer->returnBike(self::USER, self::BIKE, 1, $second->rentId);
        self::assertSame([null, $first->rentId, null, $second->rentId], array_column($this->history(), 'pairActionId'));
        self::assertCount(1, array_unique(array_column($this->history(), 'time')));
    }

    public function testRepeatedReturnDoesNotWriteAgainOrRunLocalEffects(): void
    {
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId);
        $this->assertConflict('rental_ledger_no_open_rental', fn() =>
            $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId, function (): void {
                self::fail('Duplicate return must not run local effects');
            }));
        self::assertCount(2, $this->history());
    }

    public function testCutoverBoundaryIgnoresOldStartsButUnreconciledHolderIsRejected(): void
    {
        $this->db->exec("INSERT INTO history (userId,bikeNum,action,parameter)
            VALUES (991301,99131,'RENT','1234')");
        $legacyId = $this->db->getLastInsertId();
        $this->writer = $this->makeWriter($this->db, $legacyId);
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        self::assertNotSame($legacyId, $rent->rentId);
        $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId);
        $this->db->exec('UPDATE bikes SET currentUser=991301,currentStand=NULL WHERE bikeNum=99131');
        $this->assertConflict('rental_ledger_no_open_rental', fn() =>
            $this->writer->returnBike(self::USER, self::BIKE, 1, $legacyId));
        self::assertCount(3, $this->history());
    }

    public function testBackfillAfterNewWritesDoesNotChangeTheActiveRental(): void
    {
        $this->db->exec("INSERT INTO history (userId,bikeNum,action,parameter)
            VALUES (991301,99131,'RENT','1234')");
        $oldRentId = $this->db->getLastInsertId();
        $this->db->exec("INSERT INTO history (userId,bikeNum,action,parameter)
            VALUES (991301,99131,'RETURN','1')");
        $oldReturnId = $this->db->getLastInsertId();
        $this->writer = $this->makeWriter($this->db, $oldReturnId);
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        $this->db->query('UPDATE history SET pairActionId=:rent WHERE id=:return', [
            'rent' => $oldRentId, 'return' => $oldReturnId,
        ]);
        $result = $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId);
        self::assertSame($rent->rentId, $result->rentId);
        self::assertSame([null, $oldRentId, null, $rent->rentId], array_column($this->history(), 'pairActionId'));
    }

    public function testMultipleOpenStartsAreRejectedInsteadOfPickingTheNewest(): void
    {
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        $this->db->exec("INSERT INTO history
            (userId,bikeNum,action,parameter)
            VALUES (991301,99131,'RENT','4567')");
        $this->assertConflict('rental_ledger_multiple_open_rentals', fn() =>
            $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId));
        self::assertCount(2, $this->history());
        self::assertSame(self::USER, $this->bike()['currentUser']);
    }

    public function testMissingOrDifferentBikeOpeningCannotBeUsedForReturn(): void
    {
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        $otherRent = $this->writer->rent(self::OTHER_USER, self::OTHER_BIKE, '3456');
        $before = $this->history();
        foreach ([$otherRent->rentId, PHP_INT_MAX] as $invalidRentId) {
            $this->assertConflict('rental_ledger_stale_rental', fn() =>
                $this->writer->returnBike(self::USER, self::BIKE, 2, $invalidRentId));
        }
        self::assertSame($before, $this->history());
        self::assertSame(self::USER, $this->bike()['currentUser']);
        $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId);
        self::assertSame($rent->rentId, $this->history()[2]['pairActionId']);
    }

    public function testWrongHolderAndMissingStationDoNotChangeState(): void
    {
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        $this->assertConflict('rental_ledger_inconsistent_rental', fn() =>
            $this->writer->returnBike(self::OTHER_USER, self::BIKE, 2, $rent->rentId));
        $this->assertConflict('rental_ledger_stand_not_found', fn() =>
            $this->writer->returnBike(self::USER, self::BIKE, 999999, $rent->rentId));
        self::assertCount(1, $this->history());
        self::assertSame(self::USER, $this->bike()['currentUser']);
    }

    #[DataProvider('writeFailures')]
    public function testAnyWriteFailureRollsBackBikeHistoryAndLocalEffects(string $operation, string $failAfter): void
    {
        $rentId = null;
        if ($operation === 'return') {
            $rentId = $this->writer->rent(self::USER, self::BIKE, '2345')->rentId;
        }
        $bikeBefore = $this->bike();
        $historyBefore = $this->history();
        $database = $this->databaseConfig();
        $db = new FailingLedgerDb($database['DB_DSN'], $database['DB_USER'], $database['DB_PASSWORD']);
        $db->failAfter = $failAfter;
        $writer = $this->makeWriter($db);
        $effects = static function (RentalWriteResult $result) use ($db): void {
            $db->query('UPDATE credit SET credit=credit-10 WHERE userId = :id', ['id' => $result->transition->userId]);
        };
        try {
            if ($operation === 'return') {
                $writer->returnBike(self::USER, self::BIKE, 2, $rentId, $effects);
            } else {
                $writer->rent(self::USER, self::BIKE, '3456', $effects);
            }
            self::fail('The injected failure must escape the transaction');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected failure after ' . $failAfter, $exception->getMessage());
        }
        self::assertSame($bikeBefore, $this->bike());
        self::assertSame($historyBefore, $this->history());
        self::assertSame(100.0, (float)$this->db->query('SELECT credit FROM credit WHERE userId=991301')
            ->fetchAssoc()['credit']);
        self::assertFalse($db->isTransactionActive());
    }

    public static function writeFailures(): iterable
    {
        foreach (['rent', 'return'] as $operation) {
            foreach (['UPDATE bikes', 'INSERT INTO history', 'UPDATE credit'] as $statement) {
                yield $operation . ' after ' . $statement => [$operation, $statement];
            }
        }
    }

    public function testTwoConnectionsCannotRentTheSameBike(): void
    {
        $this->writer->rent(self::USER, self::BIKE, '2345', function (): void {
            $this->startBlockedWorker('rent', self::OTHER_USER, self::BIKE);
        });
        self::assertSame(['conflict' => 'rental_ledger_bike_not_available'], $this->finishWorker());
        self::assertCount(1, $this->history());
        self::assertSame(self::USER, $this->bike()['currentUser']);
    }

    public function testTwoConnectionsCannotCloseTheSameRent(): void
    {
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        $this->writer->returnBike(self::USER, self::BIKE, 2, $rent->rentId, function () use ($rent): void {
            $this->startBlockedWorker('return', self::USER, self::BIKE, $rent->rentId);
        });
        self::assertSame(['conflict' => 'rental_ledger_no_open_rental'], $this->finishWorker());
        self::assertCount(2, $this->history());
    }

    public function testUserLockSerializesLocalLimitPolicyAcrossDifferentBikes(): void
    {
        $this->writer->rent(self::USER, self::BIKE, '2345', function (): void {
            $this->startBlockedWorker('limited-rent', self::USER, self::OTHER_BIKE);
        });
        self::assertSame(['conflict' => 'user_limit'], $this->finishWorker());
        self::assertCount(1, $this->history());
        $otherBike = $this->db->query('SELECT currentUser FROM bikes WHERE bikeNum=99132')->fetchAssoc();
        self::assertNull($otherBike['currentUser']);
    }

    public function testLocalEffectCommitsWithTheExactOpeningContext(): void
    {
        // An old row with a later timestamp must not replace the exact start for downstream pricing.
        $this->db->exec("INSERT INTO history (userId,bikeNum,action,parameter,time)
            VALUES (991301,99131,'RENT','9999','2026-09-09 12:59:00')");
        $this->writer = $this->makeWriter($this->db, $this->db->getLastInsertId());
        $rent = $this->writer->rent(self::USER, self::BIKE, '2345');
        $this->clock->sleep(3600);
        $effectRuns = 0;
        $this->writer->returnBike(
            self::USER,
            self::BIKE,
            2,
            $rent->rentId,
            function (RentalWriteResult $result) use ($rent, &$effectRuns): void {
                self::assertTrue($this->db->isTransactionActive());
                self::assertSame($rent->rentId, $result->rentId);
                self::assertSame('2026-09-09 12:00:00', $result->transition->startedAt->format('Y-m-d H:i:s'));
                $this->db->exec('UPDATE credit SET credit=credit-10 WHERE userId=991301');
                ++$effectRuns;
            },
        );
        self::assertSame(1, $effectRuns);
        self::assertFalse($this->db->isTransactionActive());
        self::assertSame(90.0, (float)$this->db->query('SELECT credit FROM credit WHERE userId=991301')
            ->fetchAssoc()['credit']);
        self::assertSame($rent->rentId, $this->history()[2]['pairActionId']);
    }

    public function testRepositoryRefusesUnlockedAccess(): void
    {
        $this->expectException(\LogicException::class);
        self::getContainer()->get(RentalLedgerRepository::class)->findOpenRentalsForUpdate(self::BIKE);
    }

    private function startBlockedWorker(string $operation, int $userId, int $bikeNum, int $rentId = 0): void
    {
        $this->worker = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/rental_ledger_worker.php',
                $operation, (string)$userId, (string)$bikeNum, (string)$rentId],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $this->pipes,
            null,
            array_merge(getenv(), $this->databaseConfig()),
        );
        self::assertIsResource($this->worker);
        $connectionId = (int)$this->readWorkerLine();
        self::assertGreaterThan(0, $connectionId);
        $deadline = microtime(true) + 5;
        do {
            // MariaDB 10.3 may wait in Statistics before exposing an INNODB_LOCK_WAITS row.
            // Observe the executing locking read, then prove it cannot finish while we hold the lock.
            $session = $this->db->query(
                'SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = :connectionId',
                ['connectionId' => $connectionId],
            )->fetchAssoc();
            if (str_contains($session['INFO'] ?? '', 'FOR UPDATE')) {
                $read = [$this->pipes[1]];
                $write = $except = [];
                self::assertSame(0, stream_select($read, $write, $except, 0, 200000));

                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
        self::fail('Expected a blocked locking read. Worker: ' . stream_get_contents($this->pipes[1])
            . stream_get_contents($this->pipes[2]));
    }

    private function finishWorker(): array
    {
        $result = json_decode($this->readWorkerLine(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        $exitCode = proc_close($this->worker);
        $this->worker = null;
        self::assertSame(0, $exitCode);

        return $result;
    }

    private function readWorkerLine(): string
    {
        $read = [$this->pipes[1]];
        $write = $except = [];
        self::assertSame(1, stream_select($read, $write, $except, 10), 'Worker output timed out');
        $line = fgets($this->pipes[1]);
        self::assertIsString($line);

        return trim($line);
    }

    /** @return array{DB_DSN: string, DB_USER: string, DB_PASSWORD: string} */
    private function databaseConfig(): array
    {
        // Dotenv values need not be exported to the process environment (notably DB_DSN in CI).
        return [
            'DB_DSN' => $_SERVER['DB_DSN'] ?? $_ENV['DB_DSN'],
            'DB_USER' => $_SERVER['DB_USER'] ?? $_ENV['DB_USER'],
            'DB_PASSWORD' => $_SERVER['DB_PASSWORD'] ?? $_ENV['DB_PASSWORD'],
        ];
    }

    private function makeWriter(DbInterface $db, int $historyStartId = 0): NormalRentalWriter
    {
        return new NormalRentalWriter(
            $db,
            new RentalLedgerRepository($db, $historyStartId),
            new NormalRentalPlanner(),
            $this->clock,
        );
    }

    private function history(): array
    {
        return $this->db->query('SELECT * FROM history WHERE bikeNum IN (99131,99132) ORDER BY id')->fetchAllAssoc();
    }

    private function bike(): array
    {
        return $this->db->query('SELECT * FROM bikes WHERE bikeNum=99131')->fetchAssoc();
    }

    private function assertConflict(string $code, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected rental ledger conflict');
        } catch (RentalLedgerConflict $exception) {
            self::assertSame($code, $exception->getMessage());
        }
    }
}
