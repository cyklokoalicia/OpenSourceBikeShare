<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Command;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
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
        $this->tester = new CommandTester($application->find('app:migrate_rental_history'));
        $this->db = self::getContainer()->get(DbInterface::class);
        $this->db->query('DELETE FROM history');
    }

    public function testPreviewApplyAndRepeatChangeOnlyUnambiguousPairs(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, null, 4],
            [110, self::REVERSED_PAIR_BIKE, Action::RENT, 111, 4],
            [111, self::REVERSED_PAIR_BIKE, Action::RETURN, null, 4],
            [120, 9120, Action::FORCE_RENT, 121, 4],
            [121, 9120, Action::FORCE_RETURN, 120, 5],
            [130, 9130, Action::RENT, null, 4],
            [131, 9130, Action::RETURN, 130, 4],
            [140, 9140, Action::RENT, null, 4],
            [141, 9140, Action::FORCE_RETURN, null, 5],
            [150, 9150, Action::FORCE_RENT, null, 4],
            [151, 9150, Action::RETURN, null, 4],
            [160, 9160, Action::RENT, null, 4],
            [161, 9160, Action::RETURN, null, 5],
            [170, 9170, Action::RENT, null, 4],
            [171, 9170, Action::RETURN, 99999, 4],
            [180, 9180, Action::RENT, null, 4],
            [181, 9180, Action::RETURN, null, 4],
            [182, 9180, Action::RETURN, 180, 4],
            [190, 9190, Action::RENT, 191, 4],
            [191, 9190, Action::RETURN, null, 4],
            [192, 9191, Action::RENT, 191, 4],
            [200, 9200, Action::RENT, null, 4],
            [201, 9200, Action::REVERT, null, 5],
            [202, 9200, Action::RENT, null, 5],
            [203, 9200, Action::RETURN, null, 5],
            [210, 9210, Action::FORCE_RETURN, null, 5],
            [220, 9220, Action::RENT, null, 4],
            [221, 9220, Action::RENT, null, 4],
            [222, 9220, Action::RETURN, null, 4],
            [230, 9230, Action::RENT, null, 4],
            [231, 9230, Action::CHANGE_CODE, null, 5],
            [232, 9230, Action::RETURN, null, 4],
            [240, 9240, Action::RENT, null, 4],
            [241, 9240, Action::RETURN, null, 4, '1999-12-31 12:00:00'],
            [250, 9250, Action::RENT, null, 4],
            [260, 9260, Action::RENT, 261, 4],
            [261, 9260, Action::RETURN, null, 4],
            [270, 9270, Action::RENT, null, 4],
            [271, 9270, Action::RETURN, null, 4],
            [280, self::CONFLICTING_LINK_BIKE, Action::RENT, null, 4],
            [281, self::CONFLICTING_LINK_BIKE, Action::RETURN, null, 4],
            [290, 9281, Action::RETURN, 280, 4],
            [999, 9990, Action::RENT, null, 4],
            [2001, 9990, Action::RETURN, null, 4],
        ]);
        for ($id = 1000; $id <= 2000; ++$id) {
            $this->insertHistory([[$id, 9991, Action::CHANGE_CODE, null, 4]]);
        }
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
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, null, 4],
            [110, self::REVERSED_PAIR_BIKE, Action::RENT, 111, 4],
            [111, self::REVERSED_PAIR_BIKE, Action::RETURN, null, 4],
        ]);
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
        $this->insertHistory([
            [280, self::CONFLICTING_LINK_BIKE, Action::RENT, null, 4],
            [281, self::CONFLICTING_LINK_BIKE, Action::RETURN, null, 4],
            [290, 9281, Action::RETURN, 280, 4],
        ]);
        $before = $this->history();
        $this->tester->execute(['--bike' => self::CONFLICTING_LINK_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertStringContainsString('other_references', $this->tester->getDisplay());
    }

    public function testSequentialChainIsSplitIntoPairsIncludingAcrossBatches(): void
    {
        $this->insertHistory([
            [10, 9100, Action::RENT, null, 4],
            [11, 9100, Action::RETURN, 10, 4],
            [12, 9100, Action::RENT, 11, 5],
            [13, 9100, Action::RETURN, 12, 5],
            [14, 9100, Action::FORCE_RENT, 13, 4],
            [15, 9100, Action::FORCE_RETURN, 14, 5],
            [1020, 9100, Action::RENT, 15, 4],
        ]);
        for ($id = 20; $id < 1020; ++$id) {
            $this->insertHistory([[$id, 9100, Action::CHANGE_CODE, null, 4]]);
        }
        $before = $this->history();
        $this->tester->execute(['--dry-run' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Chain links to clear\s+3/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+3/', $this->tester->getDisplay());

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        foreach ($expected as &$row) {
            if (in_array($row['id'], [12, 14, 1020], true)) {
                $row['pairActionId'] = null;
            }
        }
        unset($row);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Chain links cleared\s+3/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+3/', $this->tester->getDisplay());

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Chain links cleared\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Pairs updated\s+0/', $this->tester->getDisplay());
    }

    public function testChainCleanupAndMissingReturnLinkAreAppliedInOneRun(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, 101, 5],
            [103, 9100, Action::RETURN, null, 5],
        ]);
        $this->tester->execute(['--dry-run' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame([null, 100, 101, null], array_column($this->history(), 'pairActionId'));
        self::assertMatchesRegularExpression('/Pairs to update\s+1/', $this->tester->getDisplay());
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame([null, 100, null, 102], array_column($this->history(), 'pairActionId'));
        self::assertMatchesRegularExpression('/Pairs updated\s+1/', $this->tester->getDisplay());
    }

    #[DataProvider('ambiguousChains')]
    public function testAmbiguousChainsStayUnchanged(array $rows): void
    {
        $this->insertHistory($rows);
        $before = $this->history();
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
    }

    public static function ambiguousChains(): iterable
    {
        yield 'gap before next rent' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, null, 4],
            [103, 9100, Action::RENT, 101, 4],
        ]];
        yield 'duplicate closure on another bike' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, 101, 4],
            [103, 9200, Action::RETURN, 100, 4],
        ]];
        yield 'missing previous pair' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, null, 4],
            [102, 9100, Action::RENT, 101, 4],
        ]];
        yield 'different holder of previous return' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 5],
            [102, 9100, Action::RENT, 101, 4],
        ]];
        yield 'revert synthetic pair' => [[
            [99, 9100, Action::REVERT, null, 4],
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, 101, 4],
        ]];
        yield 'next rent timestamp is earlier' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, 101, 4, '1999-12-31 12:00:00'],
        ]];
    }

    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsDoNotWrite(array $options): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, null, 4],
        ]);
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

    /** @param list<array{int, int, Action, ?int, int, 5?: string}> $rows */
    private function insertHistory(array $rows): void
    {
        foreach ($rows as $row) {
            [$id, $bikeNumber, $action, $pairActionId, $userId] = $row;
            $this->db->query(
                'INSERT INTO history (id, bikeNum, action, pairActionId, userId, time, parameter)
                 VALUES (:id, :bikeNum, :action, :pairActionId, :userId, :time, :parameter)',
                [
                    'id' => $id,
                    'bikeNum' => $bikeNumber,
                    'action' => $action->value,
                    'pairActionId' => $pairActionId,
                    'userId' => $userId,
                    'time' => $row[5] ?? '2000-01-01 12:00:00',
                    'parameter' => '1234',
                ],
            );
        }
    }

    private function history(): array
    {
        return $this->db->query('SELECT * FROM history ORDER BY id')->fetchAllAssoc();
    }
}
