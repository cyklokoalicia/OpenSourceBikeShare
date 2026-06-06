<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Command;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Spec 0013: backfill of history.standId for legacy rows.
 */
class BackfillHistoryStandIdCommandTest extends BikeSharingKernelTestCase
{
    private const RETURN_BIKE = 9001;
    private const RENT_BIKE = 9002;
    private const REVERT_BIKE = 9003;
    private const ORPHAN_RENT_BIKE = 9004;
    private const OLD_BIKE = 9005;
    private const SEEDED_STAND = 5;

    private CommandTester $commandTester;
    private DbInterface $db;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $this->commandTester = new CommandTester($application->find('app:backfill_history_standid'));

        $this->db = self::getContainer()->get(DbInterface::class);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $this->db->query(
            'DELETE FROM history WHERE bikeNum IN (:b1, :b2, :b3, :b4, :b5)',
            [
                'b1' => self::RETURN_BIKE,
                'b2' => self::RENT_BIKE,
                'b3' => self::REVERT_BIKE,
                'b4' => self::ORPHAN_RENT_BIKE,
                'b5' => self::OLD_BIKE,
            ]
        );
    }

    private function insert(int $bikeNum, Action $action, string $parameter, string $time): void
    {
        $this->db->query(
            "INSERT INTO history (userId, bikeNum, action, parameter, standId, time)
             VALUES (1, :bikeNum, :action, :parameter, NULL, :time)",
            [
                'bikeNum' => $bikeNum,
                'action' => $action->value,
                'parameter' => $parameter,
                'time' => $time,
            ]
        );
    }

    private function standIdOf(int $bikeNum, Action $action): ?int
    {
        $row = $this->db->query(
            'SELECT standId FROM history WHERE bikeNum = :b AND action = :a ORDER BY id DESC LIMIT 1',
            ['b' => $bikeNum, 'a' => $action->value]
        )->fetchAssoc();

        return $row && $row['standId'] !== null ? (int)$row['standId'] : null;
    }

    public function testBackfillPopulatesAllStandBearingActions(): void
    {
        // RETURN: stand id is the numeric parameter.
        $this->insert(self::RETURN_BIKE, Action::RETURN, (string)self::SEEDED_STAND, '2018-03-01 10:00:00');
        // REVERT: parameter is "{standId}|{code}".
        $this->insert(self::REVERT_BIKE, Action::REVERT, self::SEEDED_STAND . '|1234', '2018-03-01 10:00:00');
        // RENT preceded by a RETURN to SEEDED_STAND — origin inferred.
        $this->insert(self::RENT_BIKE, Action::RETURN, (string)self::SEEDED_STAND, '2018-03-01 09:00:00');
        $this->insert(self::RENT_BIKE, Action::RENT, '4321', '2018-03-01 11:00:00');
        // RENT with no prior return — must stay NULL.
        $this->insert(self::ORPHAN_RENT_BIKE, Action::RENT, '7777', '2018-03-01 11:00:00');

        $this->commandTester->execute([]);

        $this->assertSame(self::SEEDED_STAND, $this->standIdOf(self::RETURN_BIKE, Action::RETURN));
        $this->assertSame(self::SEEDED_STAND, $this->standIdOf(self::REVERT_BIKE, Action::REVERT));
        $this->assertSame(self::SEEDED_STAND, $this->standIdOf(self::RENT_BIKE, Action::RENT));
        $this->assertNull(
            $this->standIdOf(self::ORPHAN_RENT_BIKE, Action::RENT),
            'RENT with no preceding return must remain NULL'
        );
    }

    public function testDryRunMakesNoChanges(): void
    {
        $this->insert(self::RETURN_BIKE, Action::RETURN, (string)self::SEEDED_STAND, '2018-03-01 10:00:00');

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('Dry run complete', $this->commandTester->getDisplay());
        $this->assertNull($this->standIdOf(self::RETURN_BIKE, Action::RETURN), 'Dry run must not write');
    }

    public function testSinceExcludesOlderRows(): void
    {
        $this->insert(self::OLD_BIKE, Action::RETURN, (string)self::SEEDED_STAND, '2015-01-01 10:00:00');
        $this->insert(self::RETURN_BIKE, Action::RETURN, (string)self::SEEDED_STAND, '2020-01-01 10:00:00');

        $this->commandTester->execute(['--since' => '2016-01-01 00:00:00']);

        $this->assertNull($this->standIdOf(self::OLD_BIKE, Action::RETURN), 'Pre-since row must be untouched');
        $this->assertSame(self::SEEDED_STAND, $this->standIdOf(self::RETURN_BIKE, Action::RETURN));
    }

    public function testInvalidSinceIsRejectedWithoutWriting(): void
    {
        $this->insert(self::RETURN_BIKE, Action::RETURN, (string)self::SEEDED_STAND, '2020-01-01 10:00:00');

        $exitCode = $this->commandTester->execute(['--since' => 'not-a-date']);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertNull(
            $this->standIdOf(self::RETURN_BIKE, Action::RETURN),
            'An invalid --since must abort before any write'
        );
    }
}
