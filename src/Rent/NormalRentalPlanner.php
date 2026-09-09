<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use BikeShare\Enum\Action;
use BikeShare\Rent\DTO\RentalTransition;
use BikeShare\Rent\Exception\RentalLedgerConflict;

/** Pure state transitions. Transport authorization and pricing belong to the operation layer. */
class NormalRentalPlanner
{
    public function rent(
        array $bike,
        array $openRentals,
        int $userId,
        string $newCode,
        \DateTimeImmutable $now,
    ): RentalTransition {
        $this->assertUnambiguous($openRentals);
        if ($openRentals !== [] || $bike['currentUser'] !== null || $bike['currentStand'] === null) {
            throw new RentalLedgerConflict('rental_ledger_bike_not_available');
        }
        if (!preg_match('/^[0-9]{4}$/D', $newCode)) {
            throw new \InvalidArgumentException('A lock code must contain exactly four digits.');
        }

        return new RentalTransition(
            Action::RENT,
            $userId,
            (int)$bike['bikeNum'],
            (int)$bike['currentStand'],
            $newCode,
            null,
            $now,
            $now,
        );
    }

    public function returnBike(
        array $bike,
        array $openRentals,
        int $userId,
        int $standId,
        int $expectedRentId,
        \DateTimeImmutable $now,
    ): RentalTransition {
        $this->assertUnambiguous($openRentals);
        if (count($openRentals) !== 1) {
            throw new RentalLedgerConflict('rental_ledger_no_open_rental');
        }
        $opening = $openRentals[0];
        if ((int)$opening['id'] !== $expectedRentId) {
            throw new RentalLedgerConflict('rental_ledger_stale_rental');
        }
        if (
            (int)$bike['currentUser'] !== $userId || $bike['currentStand'] !== null
            || (int)$opening['userId'] !== $userId || (int)$opening['bikeNum'] !== (int)$bike['bikeNum']
            || !in_array($opening['action'], [Action::RENT->value, Action::FORCE_RENT->value], true)
            || (int)$opening['ledgerVersion'] !== 1 || $opening['pairActionId'] !== null
            || $opening['rentalKind'] !== 'rental'
        ) {
            throw new RentalLedgerConflict('rental_ledger_inconsistent_rental');
        }
        $startedAt = new \DateTimeImmutable($opening['time'], $now->getTimezone());
        if ($startedAt > $now) {
            throw new RentalLedgerConflict('rental_ledger_time_before_start');
        }

        return new RentalTransition(
            Action::RETURN,
            $userId,
            (int)$bike['bikeNum'],
            $standId,
            (string)$standId,
            $expectedRentId,
            $startedAt,
            $now,
        );
    }

    private function assertUnambiguous(array $openRentals): void
    {
        if (count($openRentals) > 1) {
            throw new RentalLedgerConflict('rental_ledger_multiple_open_rentals');
        }
    }
}
