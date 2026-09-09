<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Rent;

use BikeShare\Rent\Exception\RentalLedgerConflict;
use BikeShare\Rent\NormalRentalPlanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalRentalPlannerTest extends TestCase
{
    #[DataProvider('invalidReturns')]
    public function testReturnRejectsInconsistentState(array $bikeChanges, array $openingChanges, string $error): void
    {
        $bike = ['bikeNum' => 7, 'currentUser' => 3, 'currentStand' => null];
        $opening = [
            'id' => 100, 'bikeNum' => 7, 'userId' => 3, 'action' => 'RENT', 'pairActionId' => null,
            'ledgerVersion' => 1, 'rentalKind' => 'rental', 'time' => '2026-09-09 12:00:00',
        ];
        $this->expectException(RentalLedgerConflict::class);
        $this->expectExceptionMessage($error);
        (new NormalRentalPlanner())->returnBike(
            array_replace($bike, $bikeChanges),
            [array_replace($opening, $openingChanges)],
            3,
            2,
            100,
            new \DateTimeImmutable('2026-09-09 12:30:00'),
        );
    }

    public static function invalidReturns(): iterable
    {
        yield 'wrong projection holder' => [['currentUser' => 4], [], 'rental_ledger_inconsistent_rental'];
        yield 'no projection holder' => [['currentUser' => null], [], 'rental_ledger_inconsistent_rental'];
        yield 'parked projection' => [['currentStand' => 1], [], 'rental_ledger_inconsistent_rental'];
        yield 'wrong opening holder' => [[], ['userId' => 4], 'rental_ledger_inconsistent_rental'];
        yield 'wrong bike' => [[], ['bikeNum' => 8], 'rental_ledger_inconsistent_rental'];
        yield 'wrong action' => [[], ['action' => 'RETURN'], 'rental_ledger_inconsistent_rental'];
        yield 'unverified opening' => [[], ['ledgerVersion' => null], 'rental_ledger_inconsistent_rental'];
        yield 'outgoing opening pair' => [[], ['pairActionId' => 99], 'rental_ledger_inconsistent_rental'];
        yield 'correction episode' => [[], ['rentalKind' => 'correction'], 'rental_ledger_inconsistent_rental'];
        yield 'service episode' => [[], ['rentalKind' => 'service'], 'rental_ledger_inconsistent_rental'];
        yield 'time before opening' => [[], ['time' => '2026-09-09 13:00:00'], 'rental_ledger_time_before_start'];
        yield 'stale intent' => [[], ['id' => 101], 'rental_ledger_stale_rental'];
    }

    public function testUnknownStationCannotBecomeAnObservedRentalOrigin(): void
    {
        $this->expectException(RentalLedgerConflict::class);
        (new NormalRentalPlanner())->rent(
            ['bikeNum' => 7, 'currentUser' => null, 'currentStand' => null],
            [],
            3,
            '1234',
            new \DateTimeImmutable(),
        );
    }
}
