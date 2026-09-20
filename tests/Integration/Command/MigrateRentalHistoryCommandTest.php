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

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->tester, $this->db);
        gc_collect_cycles();
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

    public function testEmptyServiceActionsAreReportedSeparatelyFromUnknownActionsAndReverts(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 0, Action::CREDIT, null, 0],
            [102, 0, Action::CREDIT, 0, 0],
            [103, 9100, Action::RETURN, null, 4],
            [110, 9200, Action::RENT, null, 4],
            [111, 9200, Action::CREDIT, null, 4],
            [112, 9200, Action::RETURN, null, 4],
            [120, 9300, Action::CREDIT, null, 0],
            [130, 9400, Action::REVERT, null, 4],
            [131, 9400, Action::RENT, null, 0],
            [132, 9400, Action::RETURN, null, 0],
            [140, 0, Action::CREDIT, null, 4],
        ]);
        // Reproduce the empty ENUM values preserved in legacy history.
        $this->db->query("UPDATE IGNORE history SET action = '' WHERE id IN (101, 102, 111, 120, 140)");
        $before = $this->history();

        foreach ([true, false] as $dryRun) {
            $this->tester->execute(
                $dryRun ? ['--dry-run' => true] : [],
                ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
            );
            $this->tester->assertCommandIsSuccessful();
            $display = $this->tester->getDisplay();
            self::assertMatchesRegularExpression('/Ignored empty actions without bike or user\s+2/', $display);
            self::assertStringContainsString('unknown_action: 3', $display);
            self::assertStringContainsString('revert: 1', $display);
            self::assertStringNotContainsString('revert_or_unknown_action', $display);
            self::assertStringContainsString('Skip history 111 (bike 9200): unknown_action', $display);
            self::assertStringContainsString('Skip history 112 (bike 9200): no_adjacent_start', $display);
            self::assertStringContainsString('Ignore history 101: empty action without bike or user', $display);
            self::assertMatchesRegularExpression('/Skipped returns\/events\s+7/', $display);

            $expected = $before;
            if (!$dryRun) {
                $expected[3]['pairActionId'] = 100;
            }
            self::assertSame($expected, $this->history());
        }

        $this->tester->execute(['--bike' => 9100, '--dry-run' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression(
            '/Ignored empty actions without bike or user\s+0/',
            $this->tester->getDisplay(),
        );
    }

    public function testRepeatedReturnLinksAreClearedWithoutChangingEvents(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RETURN, 100, 5],
            [1200, 9100, Action::RETURN, 100, 4],
            [1300, 9200, Action::FORCE_RENT, null, 4],
            [1301, 9200, Action::FORCE_RETURN, 1300, 5],
            [1302, 9200, Action::RETURN, 1300, 4],
        ]);
        for ($id = 150; $id < 1150; ++$id) {
            $this->insertHistory([[$id, 9100, Action::CHANGE_CODE, null, 4]]);
        }
        $before = $this->history();
        $this->tester->execute(['--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Repeated return links to clear\s+3/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+2/', $this->tester->getDisplay());
        self::assertStringContainsString('Clear repeated return 102 -> rent 100', $this->tester->getDisplay());

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        foreach ($expected as &$row) {
            if (in_array($row['id'], [102, 1200, 1302], true)) {
                $row['pairActionId'] = null;
            }
        }
        unset($row);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Repeated return links cleared\s+3/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+2/', $this->tester->getDisplay());

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Repeated return links cleared\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Pairs updated\s+0/', $this->tester->getDisplay());
    }

    public function testRepeatedReturnCleanupRespectsBikeFilter(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RETURN, 100, 4],
            [110, self::REVERSED_PAIR_BIKE, Action::RENT, null, 4],
            [111, self::REVERSED_PAIR_BIKE, Action::RETURN, 110, 4],
            [112, self::REVERSED_PAIR_BIKE, Action::RETURN, 110, 4],
        ]);
        $before = $this->history();
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        $expected[5]['pairActionId'] = null;
        self::assertSame($expected, $this->history());
    }

    #[DataProvider('unprovenRepeatedReturns')]
    public function testUnprovenRepeatedReturnLinksStayUnchanged(array $rows): void
    {
        $this->insertHistory($rows);
        $before = $this->history();
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Repeated return links cleared\s+0/', $this->tester->getDisplay());
    }

    public static function unprovenRepeatedReturns(): iterable
    {
        yield 'first closing link missing' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, null, 4],
            [102, 9100, Action::RETURN, 100, 4],
        ]];
        yield 'first return has another holder' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 5],
            [102, 9100, Action::RETURN, 100, 4],
        ]];
        yield 'time goes backwards' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RETURN, 100, 4, '1999-12-31 12:00:00'],
        ]];
        yield 'revert synthetic pair' => [[
            [99, 9100, Action::REVERT, null, 4],
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RETURN, 100, 4],
        ]];
        yield 'intervening rent' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, null, 4],
            [103, 9100, Action::RETURN, 100, 4],
        ]];
        yield 'return linked to another start' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RETURN, 999, 4],
        ]];
        yield 'return on another bike' => [[
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9200, Action::RETURN, 100, 4],
        ]];
        yield 'placeholder bike' => [[
            [100, 0, Action::RENT, null, 4],
            [101, 0, Action::RETURN, 100, 4],
            [102, 0, Action::RETURN, 100, 4],
        ]];
        yield 'larger start ID with earlier time' => [[
            [100, 9100, Action::RETURN, null, 4, '1999-12-31 12:00:00'],
            [101, 9100, Action::RETURN, 102, 4, '2000-01-01 14:00:00'],
            [102, 9100, Action::RENT, null, 4, '2000-01-01 13:00:00'],
        ]];
    }

    public function testMislinkedReturnsAreReassignedBeforeClearingObsoleteLinks(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, 101, 4],
            [103, 9100, Action::RETURN, 100, 4],
            [110, self::REVERSED_PAIR_BIKE, Action::FORCE_RENT, null, 4],
            [111, self::REVERSED_PAIR_BIKE, Action::FORCE_RETURN, 110, 6],
            [112, self::REVERSED_PAIR_BIKE, Action::RENT, 111, 5],
            [113, self::REVERSED_PAIR_BIKE, Action::RETURN, 110, 5],
            [114, self::REVERSED_PAIR_BIKE, Action::RETURN, null, 6],
            [115, self::REVERSED_PAIR_BIKE, Action::FORCE_RETURN, 112, 6],
            [150, 9100, Action::RENT, 101, 4],
            [151, 9100, Action::RETURN, 150, 4],
        ]);
        $before = $this->history();
        $this->tester->execute(
            ['--bike' => self::REVERSED_PAIR_BIKE, '--dry-run' => true],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );
        self::assertMatchesRegularExpression('/Return links to reassign\s+1/', $this->tester->getDisplay());
        self::assertStringNotContainsString('Reassign return 103', $this->tester->getDisplay());
        self::assertSame($before, $this->history());

        $this->tester->execute(['--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        $preview = $this->tester->getDisplay();
        self::assertMatchesRegularExpression('/Return links to reassign\s+2/', $preview);
        self::assertMatchesRegularExpression('/Repeated return links to clear\s+1/', $preview);
        self::assertMatchesRegularExpression('/Chain links to clear\s+3/', $preview);
        self::assertMatchesRegularExpression('/Already linked\s+5/', $preview);

        $this->tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->tester->assertCommandIsSuccessful();
        $changes = [102 => null, 103 => 102, 112 => null, 113 => 112, 115 => null, 150 => null];
        $expected = $before;
        foreach ($expected as &$row) {
            if (array_key_exists($row['id'], $changes)) {
                $row['pairActionId'] = $changes[$row['id']];
            }
        }
        unset($row);
        self::assertSame($expected, $this->history());
        $expectedOutput = strtr($preview, [
            'Previewing rental history; no changes will be written.' => 'Migrating rental history.',
            'Legacy return actions to normalize' => 'Legacy return actions normalized',
            'Cancellations to link' => 'Cancellations linked',
            'Technical links to clear' => 'Technical links cleared',
            'Return links to reassign' => 'Return links reassigned',
            'Repeated return links to clear' => 'Repeated return links cleared',
            'Chain links to clear' => 'Chain links cleared',
            'Pairs to update' => 'Pairs updated',
        ]);
        self::assertSame(
            preg_replace('/\s+/', ' ', $expectedOutput),
            preg_replace('/\s+/', ' ', $this->tester->getDisplay()),
        );

        $this->tester->execute([]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Return links reassigned\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Repeated return links cleared\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Chain links cleared\s+0/', $this->tester->getDisplay());
    }

    public function testReassignmentWithBikeFilterDoesNotChangeAnotherBike(): void
    {
        foreach ([9100, self::REVERSED_PAIR_BIKE] as $bike) {
            $this->insertHistory([
                [$bike, $bike, Action::RENT, null, 4],
                [$bike + 1, $bike, Action::RETURN, $bike, 4],
                [$bike + 2, $bike, Action::RENT, $bike + 1, 5],
                [$bike + 3, $bike, Action::RETURN, $bike, 5],
            ]);
        }
        $expected = $this->history();
        $expected[6]['pairActionId'] = null;
        $expected[7]['pairActionId'] = self::REVERSED_PAIR_BIKE + 2;
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($expected, $this->history());
    }

    #[DataProvider('uncertainMislinkedReturns')]
    public function testMislinkedReturnRequiresAnUnambiguousNewPair(array $changes, array $extra): void
    {
        $rows = [
            [100, 9100, Action::RENT, null, 4],
            [110, 9100, Action::RETURN, 100, 4],
            [120, 9100, Action::RENT, 110, 5],
            [130, 9100, Action::RETURN, 100, 5],
        ];
        foreach ($changes as $index => $fields) {
            $rows[$index] = array_replace($rows[$index], $fields);
        }
        $this->insertHistory([...$rows, ...$extra]);
        $this->tester->execute(['--bike' => 9100, '--dry-run' => true]);
        self::assertMatchesRegularExpression('/Return links to reassign\s+0/', $this->tester->getDisplay());
        $this->tester->execute(['--bike' => 9100]);
        $this->tester->assertCommandIsSuccessful();
        $return = $this->db->query('SELECT pairActionId FROM history WHERE id = 130')->fetchAssoc();
        self::assertSame(100, $return['pairActionId']);
    }

    public static function uncertainMislinkedReturns(): iterable
    {
        yield 'no backward link proving chain' => [[2 => [3 => null]], []];
        yield 'new holder differs' => [[3 => [4 => 6]], []];
        yield 'old holder differs' => [[1 => [4 => 6]], []];
        yield 'unknown new holder' => [[2 => [4 => 0], 3 => [4 => 0]], []];
        yield 'new return is forced' => [[3 => [2 => Action::FORCE_RETURN]], []];
        yield 'old pair missing' => [[1 => [3 => null]], []];
        yield 'backwards new return time' => [[3 => [5 => '1999-12-31 12:00:00']], []];
        yield 'backwards new start time' => [[2 => [5 => '1999-12-31 12:00:00']], []];
        yield 'old pair after revert' => [[], [[99, 9100, Action::REVERT, null, 4]]];
        yield 'intervening revert' => [[], [[125, 9100, Action::REVERT, null, 5]]];
        yield 'extra old start reference' => [[], [[140, 9200, Action::RETURN, 100, 4]]];
        yield 'extra old return reference' => [[], [[140, 9200, Action::RENT, 110, 4]]];
        yield 'extra new return reference' => [[], [[140, 9200, Action::RENT, 130, 4]]];
        yield 'new start reference from another bike' => [[], [[140, 9200, Action::FORCE_RETURN, 120, 4]]];
        yield 'new start reference from service action' => [[], [[140, 9100, Action::CHANGE_CODE, 120, 4]]];
        yield 'new start has an earlier reference' => [[], [[115, 9100, Action::RETURN, 120, 5]]];
        yield 'later return after another start' => [[], [
            [140, 9100, Action::RENT, null, 4],
            [150, 9100, Action::FORCE_RETURN, 120, 4],
        ]];
        yield 'later return after revert' => [[], [
            [140, 9100, Action::REVERT, null, 4],
            [150, 9100, Action::FORCE_RETURN, 120, 4],
        ]];
        yield 'later return time goes backwards' => [[], [
            [140, 9100, Action::FORCE_RETURN, 120, 4, '1999-12-31 12:00:00'],
        ]];
    }

    public function testInterruptedReassignmentFinishesCleanupOnRerun(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::RENT, 101, 5],
            [103, 9100, Action::RETURN, 102, 5],
            [104, 9100, Action::FORCE_RETURN, 102, 6],
        ]);
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame([null, 100, null, 102, null], array_column($this->history(), 'pairActionId'));
        self::assertMatchesRegularExpression('/Return links reassigned\s+0/', $this->tester->getDisplay());
    }

    #[DataProvider('technicalRevertLayouts')]
    public function testRevertLinksTheCanceledRentalAndClearsOnlyRestorationLinks(
        bool $swapped,
        string $returnTime,
    ): void {
        $time = '2000-01-01 12:00:00';
        $revertId = $swapped ? 102 : 101;
        $technicalId = $swapped ? 101 : 102;
        $this->insertHistory([
            [90, 9100, Action::RENT, null, 4, '1999-12-31 10:00:00'],
            [91, 9100, Action::RETURN, 90, 4, '1999-12-31 11:00:00'],
            [100, 9100, Action::RENT, 91, 4, '2000-01-01 11:00:00'],
            [$revertId, 9100, Action::REVERT, null, 5, $time, '23|0456'],
            [$technicalId, 9100, Action::RENT, 91, 0, $time, '456'],
            [103, 9100, Action::RETURN, $returnTime < $time ? 90 : 100, 0, $returnTime, '23'],
            [104, 9100, Action::FORCE_RETURN, $technicalId, 5],
            [105, 9100, Action::FORCE_RETURN, $technicalId, 5],
            [110, 9100, Action::RENT, null, 4],
            [111, 9100, Action::RETURN, 110, 4],
        ]);
        $before = $this->history();
        $this->tester->execute(['--dry-run' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Cancellations to link\s+1/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Technical links to clear\s+2/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Repeated return links to clear\s+2/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Chain links to clear\s+1/', $this->tester->getDisplay());
        $expected = $before;
        $changes = [100 => null, $revertId => 100, $technicalId => null, 103 => null, 104 => null, 105 => null];
        foreach ($expected as &$row) {
            if (array_key_exists($row['id'], $changes)) {
                $row['pairActionId'] = $changes[$row['id']];
            }
        }
        unset($row);
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Cancellations linked\s+1/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+2/', $this->tester->getDisplay());
        self::assertStringNotContainsString('superseded_start:', $this->tester->getDisplay());
        $this->tester->execute([]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Cancellations linked\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked cancellations\s+1/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Technical links cleared\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Repeated return links cleared\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Chain links cleared\s+0/', $this->tester->getDisplay());
    }

    public static function technicalRevertLayouts(): iterable
    {
        yield 'regular insertion order' => [false, '2000-01-01 12:00:00'];
        yield 'technical start inserted before revert' => [true, '2000-01-01 12:00:00'];
        yield 'edited technical return timestamp' => [false, '1999-12-31 12:00:00'];
    }

    #[DataProvider('unprovenRevertGroups')]
    public function testRevertDoesNotInferTechnicalEventsFromAdjacencyAlone(array $changes, array $extra): void
    {
        $time = '2000-01-01 12:00:00';
        $rows = [
            [100, 9100, Action::RENT, null, 4, $time],
            [110, 9100, Action::REVERT, null, 5, $time, '23|0456'],
            [120, 9100, Action::RENT, null, 0, $time, '456'],
            [130, 9100, Action::RETURN, 100, 0, $time, '23'],
        ];
        foreach ($changes as $index => $fields) {
            $rows[$index] = array_replace($rows[$index], $fields);
        }
        $this->insertHistory([...$rows, ...$extra]);
        $before = $this->history();
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Recognized technical revert events\s+0/', $this->tester->getDisplay());
    }

    public static function unprovenRevertGroups(): iterable
    {
        yield 'different code' => [[2 => [6 => '457']], []];
        yield 'different stand' => [[3 => [6 => '24']], []];
        yield 'malformed revert parameters' => [[1 => [6 => '23|0456|extra']], []];
        yield 'missing stand' => [[1 => [6 => '0|0456'], 3 => [6 => '0']], []];
        yield 'different technical holders' => [[3 => [4 => 5]], []];
        yield 'unrelated technical actor' => [[2 => [4 => 4], 3 => [4 => 4]], []];
        yield 'later rental by the administrator' => [[
            2 => [4 => 5, 5 => '2000-01-02 12:00:00'],
            3 => [4 => 5, 5 => '2000-01-02 13:00:00'],
        ], []];
        yield 'technical start precedes cancellation in time' => [[2 => [5 => '1999-12-31 12:00:00']], []];
        yield 'return on another bike' => [[3 => [1 => 9200]], []];
        yield 'intervening real start' => [[], [[125, 9100, Action::RENT, null, 4]]];
    }

    public function testRevertPreservesUnrelatedClosingReferencesAndRespectsBikeFilter(): void
    {
        $time = '2000-01-01 12:00:00';
        foreach ([9100, self::REVERSED_PAIR_BIKE] as $bike) {
            $this->insertHistory([
                [$bike, $bike, Action::RENT, null, 4],
                [$bike + 1, $bike, Action::REVERT, null, 5, $time, '23|0456'],
                [$bike + 2, $bike, Action::RENT, null, 5, $time, '456'],
                [$bike + 3, $bike, Action::RETURN, $bike, 5, $time, '23'],
            ]);
        }
        $this->insertHistory([[9200, 9200, Action::RETURN, self::REVERSED_PAIR_BIKE, 4]]);
        $expected = $this->history();
        $expected[7]['pairActionId'] = null;
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($expected, $this->history());
        self::assertStringContainsString('unpaired_revert: 1', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Cancellations linked\s+0/', $this->tester->getDisplay());
    }

    public function testInterruptedCancellationFinishesCleanupWithoutOverwritingItsLink(): void
    {
        $time = '2000-01-01 12:00:00';
        $this->insertHistory([
            [90, 9100, Action::RETURN, null, 4, '1999-12-31 12:00:00'],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::REVERT, 100, 5, $time, '23|0456'],
            [102, 9100, Action::RENT, 90, 0, $time, '456'],
            [103, 9100, Action::RETURN, 100, 0, $time, '23'],
            [104, 9100, Action::FORCE_RETURN, 102, 5],
        ]);
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame([null, null, 100, null, null, null], array_column($this->history(), 'pairActionId'));
        self::assertMatchesRegularExpression('/Already linked cancellations\s+1/', $this->tester->getDisplay());
    }

    public function testSupersededStartLosesOnlyItsProvenBackwardLink(): void
    {
        $this->insertHistory([
            [90, 9100, Action::RETURN, null, 4, '1999-12-31 12:00:00'],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::FORCE_RENT, null, 5],
            [102, 9100, Action::RETURN, 101, 5],
        ]);
        $expected = $this->history();
        $expected[1]['pairActionId'] = null;
        $this->tester->execute([]);
        self::assertSame($expected, $this->history());
        self::assertStringContainsString('superseded_start: 1', $this->tester->getDisplay());
    }

    public function testExplicitPairWithReversedIdsIsNotReportedAsAnUnclosedRental(): void
    {
        $this->insertHistory([
            [90, 9100, Action::RENT, null, 4, '2000-01-01 09:00:00'],
            [91, 9100, Action::RETURN, 90, 4, '2000-01-01 10:00:00'],
            [100, 9100, Action::RETURN, 101, 4, '2000-01-01 12:00:00'],
            [101, 9100, Action::RENT, 91, 4, '2000-01-01 11:00:00'],
            [102, 9100, Action::RENT, null, 5, '2000-01-01 13:00:00'],
            [103, 9100, Action::RETURN, 102, 5, '2000-01-01 14:00:00'],
        ]);
        $expected = $this->history();
        $expected[3]['pairActionId'] = null;
        $this->tester->execute([]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Already linked\s+3/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Skipped returns\/events\s+0/', $this->tester->getDisplay());
        $this->tester->execute([]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Chain links cleared\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+3/', $this->tester->getDisplay());
    }

    #[DataProvider('ambiguousReversedIdPairs')]
    public function testReversedIdPairStillRequiresHolderTimeAndUniqueReferences(array $rows): void
    {
        $this->insertHistory($rows);
        $before = $this->history();
        $this->tester->execute(['--bike' => 9100]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Already linked\s+0/', $this->tester->getDisplay());
    }

    public static function ambiguousReversedIdPairs(): iterable
    {
        yield 'return predates start' => [[
            [100, 9100, Action::RETURN, 101, 4, '2000-01-01 11:00:00'],
            [101, 9100, Action::RENT, null, 4, '2000-01-01 12:00:00'],
        ]];
        yield 'different holder' => [[
            [100, 9100, Action::RETURN, 101, 5, '2000-01-01 12:00:00'],
            [101, 9100, Action::RENT, null, 4, '2000-01-01 11:00:00'],
        ]];
        yield 'reference to start from another bike' => [[
            [100, 9100, Action::RETURN, 101, 4, '2000-01-01 12:00:00'],
            [101, 9100, Action::RENT, null, 4, '2000-01-01 11:00:00'],
            [102, 9200, Action::RETURN, 101, 4],
        ]];
        yield 'reference to return from another bike' => [[
            [100, 9100, Action::RETURN, 101, 4, '2000-01-01 12:00:00'],
            [101, 9100, Action::RENT, null, 4, '2000-01-01 11:00:00'],
            [102, 9200, Action::RENT, 100, 4],
        ]];
    }

    public function testRevertAfterACompletedRentalDoesNotCancelItAgain(): void
    {
        $time = '2000-01-01 12:00:00';
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::FORCE_RETURN, 100, 5],
            [102, 9100, Action::REVERT, null, 5, $time, '23|0456'],
            [103, 9100, Action::RENT, null, 0, $time, '456'],
            [104, 9100, Action::RETURN, null, 0, $time, '23'],
        ]);
        $before = $this->history();
        $this->tester->execute([]);
        self::assertSame($before, $this->history());
        self::assertStringContainsString('unpaired_revert: 1', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Cancellations linked\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+1/', $this->tester->getDisplay());
    }

    public function testLegacyReturnActionNormalizationPreservesEveryOtherField(): void
    {
        $time = '2014-10-01 12:00:00';
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4, $time],
            [101, 9100, Action::CHANGE_CODE, null, 4, $time],
            [102, 9100, Action::RETURN, 100, 5, $time],
        ]);
        $before = $this->history();
        $this->tester->execute(['--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Legacy return actions to normalize\s+1/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+1/', $this->tester->getDisplay());
        self::assertStringContainsString(
            'Normalize legacy return 102: RETURN -> FORCERETURN',
            $this->tester->getDisplay(),
        );
        self::assertStringNotContainsString('different_holder:', $this->tester->getDisplay());
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        $expected[2]['action'] = Action::FORCE_RETURN->value;
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Legacy return actions normalized\s+1/', $this->tester->getDisplay());
        $this->tester->execute(['--dry-run' => true]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Legacy return actions to normalize\s+0/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+1/', $this->tester->getDisplay());
    }

    public function testLegacyReturnActionNormalizationRespectsBikeFilter(): void
    {
        $time = '2014-10-01 12:00:00';
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4, $time],
            [101, 9100, Action::RETURN, 100, 5, $time],
            [110, self::REVERSED_PAIR_BIKE, Action::RENT, null, 4, $time],
            [111, self::REVERSED_PAIR_BIKE, Action::RETURN, 110, 5, $time],
        ]);
        $before = $this->history();
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE, '--dry-run' => true]);
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Legacy return actions to normalize\s+1/', $this->tester->getDisplay());
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        $expected[3]['action'] = Action::FORCE_RETURN->value;
        self::assertSame($expected, $this->history());
    }

    #[DataProvider('unprovenLegacyReturns')]
    public function testLegacyReturnActionNormalizationPreservesUncertainEvents(array $changes, array $extra): void
    {
        $time = '2014-10-01 12:00:00';
        $rows = [
            [100, 9100, Action::RENT, null, 4, $time],
            [110, 9100, Action::RETURN, 100, 5, $time],
        ];
        foreach ($changes as $index => $fields) {
            $rows[$index] = array_replace($rows[$index], $fields);
        }
        $this->insertHistory([...$rows, ...$extra]);
        $before = $this->history();
        $this->tester->execute(['--bike' => 9100]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Legacy return actions normalized\s+0/', $this->tester->getDisplay());
    }

    public static function unprovenLegacyReturns(): iterable
    {
        yield 'same holder' => [[1 => [4 => 4]], []];
        yield 'missing closing link' => [[1 => [3 => null]], []];
        yield 'another closing target' => [[1 => [3 => 99999]], []];
        yield 'conflicting outgoing start link' => [[0 => [3 => 99999]], []];
        yield 'unknown holder' => [[0 => [4 => 0]], []];
        yield 'unknown return actor' => [[1 => [4 => 0]], []];
        yield 'before approved legacy period' => [[
            0 => [5 => '2013-10-01 12:00:00'], 1 => [5 => '2013-10-01 12:00:00'],
        ], []];
        yield 'forced actions introduction boundary' => [[1 => [5 => '2014-11-12 00:00:00']], []];
        yield 'modern different holder' => [[1 => [5 => '2026-10-01 12:00:00']], []];
        yield 'return predates start' => [[1 => [5 => '2014-09-30 12:00:00']], []];
        yield 'start follows revert' => [[], [[99, 9100, Action::REVERT, null, 4, '2014-10-01 12:00:00']]];
        yield 'intervening cancellation' => [[], [[105, 9100, Action::REVERT, null, 4, '2014-10-01 12:00:00']]];
        yield 'extra reference from another bike to start' => [[], [
            [120, 9200, Action::RETURN, 100, 4, '2014-10-01 12:00:00'],
        ]];
        yield 'extra reference from another bike to return' => [[], [
            [120, 9200, Action::RENT, 110, 4, '2014-10-01 12:00:00'],
        ]];
    }

    public function testInitialAndUnlinkedRepeatedReturnsAreReportedWithoutInventingStarts(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RETURN, null, 4],
            [101, 9100, Action::RETURN, null, 4],
        ]);
        $before = $this->history();
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertStringContainsString('initial_return: 1', $this->tester->getDisplay());
        self::assertStringContainsString('return_after_return: 1', $this->tester->getDisplay());
    }

    public function testForcedReturnLinksAreClearedAcrossUnlinkedParkedReturns(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, null, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9100, Action::FORCE_RETURN, 100, 5],
            [103, 9100, Action::FORCE_RETURN, null, 5],
            [104, 9100, Action::FORCE_RETURN, 100, 5],
            [105, 9100, Action::RETURN, 100, 4],
            [106, 9100, Action::RENT, null, 4],
            [107, 9100, Action::FORCE_RETURN, 106, 5],
        ]);
        $before = $this->history();
        $this->tester->execute(['--dry-run' => true]);
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Repeated return links to clear\s+3/', $this->tester->getDisplay());
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        foreach ($expected as &$row) {
            if (in_array($row['id'], [102, 104, 105], true)) {
                $row['pairActionId'] = null;
            }
        }
        unset($row);
        self::assertSame($expected, $this->history());
        self::assertStringContainsString('force_return_after_return: 3', $this->tester->getDisplay());
        $this->tester->execute([]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Repeated return links cleared\s+0/', $this->tester->getDisplay());
    }

    public function testOwnClosingPairAllowsClearingLinksWithoutAPreviousRental(): void
    {
        $this->insertHistory([
            [90, self::REVERSED_PAIR_BIKE, Action::RETURN, null, 4],
            [100, self::REVERSED_PAIR_BIKE, Action::RENT, 90, 4],
            [101, self::REVERSED_PAIR_BIKE, Action::RETURN, 100, 4],
            [102, self::REVERSED_PAIR_BIKE, Action::RENT, 101, 4],
            [103, self::REVERSED_PAIR_BIKE, Action::FORCE_RENT, 101, 5],
            [104, self::REVERSED_PAIR_BIKE, Action::FORCE_RETURN, 103, 4],
            [200, 9200, Action::RETURN, null, 4],
            [201, 9200, Action::RENT, 200, 4],
            [202, 9200, Action::RETURN, 201, 4],
        ]);
        $before = $this->history();
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE, '--dry-run' => true]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Chain links to clear\s+3/', $this->tester->getDisplay());
        self::assertMatchesRegularExpression('/Already linked\s+2/', $this->tester->getDisplay());
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        foreach ($expected as &$row) {
            if (in_array($row['id'], [100, 102, 103], true)) {
                $row['pairActionId'] = null;
            }
        }
        unset($row);
        self::assertSame($expected, $this->history());
        $this->tester->execute(['--bike' => self::REVERSED_PAIR_BIKE]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Chain links cleared\s+0/', $this->tester->getDisplay());
    }

    #[DataProvider('ambiguousOwnPairs')]
    public function testOwnPairCleanupPreservesUncertainLinks(array $rows): void
    {
        $this->insertHistory($rows);
        $before = $this->history();
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
    }

    public static function ambiguousOwnPairs(): iterable
    {
        yield 'another closing reference from another bike' => [[
            [90, 9100, Action::RETURN, null, 4],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [102, 9200, Action::RETURN, 100, 4],
        ]];
        yield 'missing own closing link' => [[
            [90, 9100, Action::RETURN, null, 4],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::RETURN, null, 4],
        ]];
        yield 'return holder differs' => [[
            [90, 9100, Action::RETURN, null, 4],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::RETURN, 100, 5],
        ]];
        yield 'old target is not a return' => [[
            [90, 9100, Action::CHANGE_CODE, null, 4],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::RETURN, 100, 4],
        ]];
        yield 'old target belongs to another bike' => [[
            [90, 9200, Action::RETURN, null, 4],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::RETURN, 100, 4],
        ]];
        yield 'old return time is later than start' => [[
            [90, 9100, Action::RETURN, null, 4, '2000-01-02 12:00:00'],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::RETURN, 100, 4],
        ]];
        yield 'later target ID with equal time is ambiguous' => [[
            [100, 9100, Action::RENT, 130, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [130, 9100, Action::RETURN, null, 4],
        ]];
        yield 'revert synthetic start' => [[
            [90, 9100, Action::RETURN, null, 4],
            [99, 9100, Action::REVERT, null, 5],
            [100, 9100, Action::RENT, 90, 4],
            [101, 9100, Action::RETURN, 100, 4],
        ]];
    }

    public function testOwnPairCleanupAcceptsALargerOldReturnIdOnlyWithEarlierTime(): void
    {
        $this->insertHistory([
            [100, 9100, Action::RENT, 130, 4],
            [101, 9100, Action::RETURN, 100, 4],
            [130, 9100, Action::RETURN, null, 4, '1999-12-31 12:00:00'],
        ]);
        $before = $this->history();
        $this->tester->execute(['--dry-run' => true]);
        self::assertSame($before, $this->history());
        self::assertMatchesRegularExpression('/Chain links to clear\s+1/', $this->tester->getDisplay());
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $expected = $before;
        $expected[0]['pairActionId'] = null;
        self::assertSame($expected, $this->history());
        $this->tester->execute([]);
        self::assertSame($expected, $this->history());
        self::assertMatchesRegularExpression('/Chain links cleared\s+0/', $this->tester->getDisplay());
    }

    public function testTechnicalAndInitialForcedReturnsHaveDistinctReasons(): void
    {
        $this->insertHistory([
            [90, 9100, Action::FORCE_RETURN, null, 5],
            [100, 9200, Action::REVERT, null, 4],
            [101, 9200, Action::RENT, null, 0],
            [102, 9200, Action::RETURN, null, 0],
            [110, 9300, Action::REVERT, null, 4],
            [111, 9300, Action::RETURN, null, 0],
            [120, 9400, Action::REVERT, null, 4],
            [121, 9400, Action::RENT, null, 0],
        ]);
        $before = $this->history();
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        self::assertSame($before, $this->history());
        self::assertStringContainsString('initial_force_return: 1', $this->tester->getDisplay());
        self::assertStringContainsString('return_after_revert: 2', $this->tester->getDisplay());
        self::assertStringNotContainsString('no_return:', $this->tester->getDisplay());
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

    /** @param list<array{int, int, Action, ?int, int, 5?: string, 6?: string}> $rows */
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
                    'parameter' => $row[6] ?? '1234',
                ],
            );
        }
    }

    private function history(): array
    {
        return $this->db->query('SELECT * FROM history ORDER BY id')->fetchAllAssoc();
    }
}
