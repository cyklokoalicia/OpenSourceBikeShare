<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Command;

use BikeShare\Db\DbInterface;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class MigrateRentalHistoryCommandTest extends BikeSharingKernelTestCase
{
    private const REVERSED_PAIR_BIKE = 9110;
    private const CONFLICTING_LINK_BIKE = 9280;
    private CommandTester $tester;
    private DbInterface $db;

    protected function setUp(): void
    {
        parent::setUp();
        $application = new Application(self::$kernel);
        $fixtures = new CommandTester($application->find('load:fixtures'));
        $fixtures->execute([]);
        $fixtures->assertCommandIsSuccessful();
        $this->tester = new CommandTester($application->find('app:migrate_rental_history'));
        $this->db = self::getContainer()->get(DbInterface::class);
    }

    public function testPreviewApplyAndRepeatChangeOnlyUnambiguousPairs(): void
    {
        $before = $this->history();
        $bikes = $this->db->query('SELECT * FROM bikes ORDER BY bikeNum')->fetchAllAssoc();
        $credit = $this->db->query('SELECT * FROM credit ORDER BY userId')->fetchAllAssoc();
        $sent = $this->db->query('SELECT * FROM sent ORDER BY id')->fetchAllAssoc();

        $this->tester->execute(
            ['--dry-run' => true],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertStringContainsString('Pair return 111 -> rent 110', $this->tester->getDisplay());
        $reasons = [
            'different_holder', 'conflicting_pair', 'other_references', 'start_after_revert', 'time_goes_backwards',
        ];
        foreach ($reasons as $reason) {
            self::assertStringContainsString($reason, $this->tester->getDisplay());
        }

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        $changes = [
            101 => 100, 110 => null, 111 => 110, 120 => null, 141 => 140, 151 => 150,
            222 => 221, 232 => 230, 260 => null, 261 => 260, 271 => 270, 2001 => 999,
        ];
        foreach ($expected as &$row) {
            if (array_key_exists($row['id'], $changes)) {
                $row['pairActionId'] = $changes[$row['id']];
            }
        }
        unset($row);
        self::assertSame($expected, $this->history());
        self::assertSame($bikes, $this->db->query('SELECT * FROM bikes ORDER BY bikeNum')->fetchAllAssoc());
        self::assertSame($credit, $this->db->query('SELECT * FROM credit ORDER BY userId')->fetchAllAssoc());
        self::assertSame($sent, $this->db->query('SELECT * FROM sent ORDER BY id')->fetchAllAssoc());

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Pairs updated\s+0/', $this->tester->getDisplay());
    }

    public function testBikeFilterLeavesOtherHistoryUnchanged(): void
    {
        $before = $this->history();
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        foreach ($expected as &$row) {
            if ($row['id'] === 110) {
                $row['pairActionId'] = null;
            } elseif ($row['id'] === 111) {
                $row['pairActionId'] = 110;
            }
        }
        unset($row);
        self::assertSame($expected, $this->history());
    }

    public function testReferencesFromAnotherBikePreventMigration(): void
    {
        $before = $this->history();
        $this->tester->execute(['--bike' => self::CONFLICTING_LINK_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertStringContainsString('other_references', $this->tester->getDisplay());
    }

    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsDoNotWrite(array $options): void
    {
        $before = $this->history();
        self::assertSame(Command::INVALID, $this->tester->execute($options));
        self::assertSame($before, $this->history());
    }

    public static function invalidOptions(): iterable
    {
        yield 'zero bike' => [['--bike' => 0]];
        yield 'negative bike' => [['--bike' => -1]];
        yield 'non-integer bike' => [['--bike' => '1.5']];
        yield 'invalid bike' => [['--bike' => 'no']];
    }

    private function history(): array
    {
        return $this->db->query('SELECT * FROM history ORDER BY id')->fetchAllAssoc();
    }
}
