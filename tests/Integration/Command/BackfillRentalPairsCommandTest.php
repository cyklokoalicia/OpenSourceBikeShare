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

class BackfillRentalPairsCommandTest extends BikeSharingKernelTestCase
{
    private const LAST_HISTORY_ID = 2001;
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
        $this->tester = new CommandTester($application->find('app:backfill_rental_pairs'));
        $this->db = self::getContainer()->get(DbInterface::class);
    }

    public function testPreviewApplyAndRepeatChangeOnlyUnambiguousPairs(): void
    {
        $before = $this->history();
        $bikes = $this->db->query('SELECT * FROM bikes ORDER BY bikeNum')->fetchAllAssoc();
        $credit = $this->db->query('SELECT * FROM credit ORDER BY userId')->fetchAllAssoc();
        $sent = $this->db->query('SELECT * FROM sent ORDER BY id')->fetchAllAssoc();

        $this->tester->execute(
            ['--to-id' => self::LAST_HISTORY_ID],
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

        $this->tester->execute(['--to-id' => self::LAST_HISTORY_ID, '--apply' => true]);
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

        $this->tester->execute(['--to-id' => self::LAST_HISTORY_ID, '--apply' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Pairs updated\s+0/', $this->tester->getDisplay());
    }

    public function testBikeFilterAndBoundaryLeaveOtherHistoryUnchanged(): void
    {
        $before = $this->history();
        $this->tester->execute(['--to-id' => 110, '--bike' => self::REVERSED_PAIR_BIKE, '--apply' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());

        $this->tester->execute(['--to-id' => 111, '--bike' => self::REVERSED_PAIR_BIKE, '--apply' => true]);
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

    public function testReferencesBeyondBoundaryAndFromAnotherBikePreventBackfill(): void
    {
        $before = $this->history();
        $this->tester->execute(['--to-id' => 281, '--bike' => self::CONFLICTING_LINK_BIKE, '--apply' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertStringContainsString('other_references', $this->tester->getDisplay());
    }

    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsDoNotWrite(array $options): void
    {
        $before = $this->history();
        self::assertSame(Command::INVALID, $this->tester->execute($options + ['--apply' => true]));
        self::assertSame($before, $this->history());
    }

    public static function invalidOptions(): iterable
    {
        yield 'missing boundary' => [[]];
        yield 'zero boundary' => [['--to-id' => 0]];
        yield 'negative boundary' => [['--to-id' => -1]];
        yield 'non-integer boundary' => [['--to-id' => '1.5']];
        yield 'invalid bike' => [['--to-id' => self::LAST_HISTORY_ID, '--bike' => 'no']];
    }

    private function history(): array
    {
        return $this->db->query('SELECT * FROM history ORDER BY id')->fetchAllAssoc();
    }
}
