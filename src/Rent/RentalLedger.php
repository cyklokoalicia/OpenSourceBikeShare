<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use BikeShare\Enum\Action;
use BikeShare\Rent\Enum\BikeRentalState;
use BikeShare\Repository\HistoryRepository;

/**
 * The spec 0013 rental state machine, and the sole writer of the rental side of the `history`
 * ledger.
 *
 * A bike is always in one of two states — {@see BikeRentalState} — derived from whether it has
 * an *open rental* (a RENT/FORCERENT with no later RETURN/FORCERETURN). Every rent/return/revert
 * goes through here (never bare addItem() in the engine), so the per-bike event stream stays a
 * valid PARKED↔ON_TRIP alternation and the stored facts (standId, pairActionId) cannot drift:
 *
 *   PARKED  --rent-->          ON_TRIP    RENT (standId = origin, no pair)
 *   ON_TRIP --return-->        PARKED     RETURN (standId = dest, pair = the closed rent)
 *   PARKED  --force-return-->  PARKED     FORCERETURN relocation (pair = null, closes nothing)
 *   ON_TRIP --force-rent-->    ON_TRIP    synthetic close of the abandoned trip, then re-open
 *   ON_TRIP --revert-->        PARKED     REVERT + synthetic RENT/RETURN; original rent cancelled
 *
 * The rent/return events dispatch on the current state via an exhaustive `match` (so the table
 * above is executed, not just described — an unhandled state fails loudly). Transition
 * *legality* (e.g. renting an already-rented bike needs force) stays in the engine's guards,
 * which return user-facing errors; this class owns only the resulting ledger writes.
 */
class RentalLedger
{
    public function __construct(
        private readonly HistoryRepository $historyRepository,
    ) {
    }

    /**
     * Record a rent and return the new RENT row id.
     *
     * A rent while already ON_TRIP is only reachable via a forced hand-over (the normal path
     * guards against it); the abandoned trip is closed first so no rental is left dangling (INV2).
     */
    public function recordRent(int $userId, int $bikeId, bool $force, string $code, ?int $originStand): int
    {
        $openRentId = $this->historyRepository->findOpenRentId($bikeId);

        // Transition on a rent event, dispatched by the current state (exhaustive — a new
        // state would force a compile-time-style failure here):
        match ($this->stateOf($openRentId)) {
            // ON_TRIP: only reachable via a forced hand-over — close the abandoned trip first
            // so it is not left dangling (INV2), then re-open below.
            BikeRentalState::ON_TRIP => $this->closeAbandonedRental($userId, $bikeId, $openRentId),
            // PARKED: nothing open; just open the new trip below.
            BikeRentalState::PARKED => null,
        };

        return $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RENT : Action::RENT,
            $code,
            $originStand,
            null,
        );
    }

    /**
     * Record a return. From ON_TRIP it closes the open rent (pair = that rent); from PARKED it is
     * a forced relocation that closes nothing (pair = null) — INV3.
     */
    public function recordReturn(int $userId, int $bikeId, bool $force, int $standId): void
    {
        $openRentId = $this->historyRepository->findOpenRentId($bikeId);

        // Transition on a return event, dispatched by the current state → the rent this return
        // pairs to (exhaustive):
        $pairActionId = match ($this->stateOf($openRentId)) {
            BikeRentalState::ON_TRIP => $openRentId, // closes the open rent
            BikeRentalState::PARKED => null,         // forced relocation closes nothing (INV3)
        };

        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RETURN : Action::RETURN,
            (string)$standId,
            $standId,
            $pairActionId,
        );
    }

    /**
     * Record a revert: a REVERT marker (pointing at the cancelled rental) plus a synthetic
     * RENT/RETURN pair that restores the bike to its previous stand and code.
     */
    public function recordRevert(int $userId, int $bikeId, int $standId, string $code): void
    {
        $cancelledRentId = $this->historyRepository->findOpenRentId($bikeId);

        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            Action::REVERT,
            sprintf('%s|%s', $standId, $code),
            $standId,
            $cancelledRentId,
        );

        $syntheticRentId = $this->historyRepository->addItem(
            $userId,
            $bikeId,
            Action::RENT,
            $code,
            $standId,
            null,
        );

        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            Action::RETURN,
            (string)$standId,
            $standId,
            $syntheticRentId,
        );
    }

    /**
     * Where and with what code a bike should be restored on revert: its last-known stand and
     * last rent code. Null when either is missing (nothing to revert to).
     *
     * @return array{standId: int, standName: string, code: string}|null
     */
    public function findRevertTarget(int $bikeId): ?array
    {
        $lastReturn = $this->historyRepository->findLastReturnStand($bikeId);
        $code = $this->historyRepository->findLastRentCode($bikeId);

        if ($lastReturn === null || $code === null) {
            return null;
        }

        return [
            'standId' => $lastReturn['standId'],
            'standName' => $lastReturn['standName'],
            'code' => $code,
        ];
    }

    private function stateOf(?int $openRentId): BikeRentalState
    {
        return $openRentId === null ? BikeRentalState::PARKED : BikeRentalState::ON_TRIP;
    }

    /**
     * Synthesize a closing FORCE_RETURN for an open rental superseded by a forced hand-over.
     * The destination is the bike's last-known stand (the decided placeholder — it has no real
     * stand while ON_TRIP), so the abandoned rental gets a terminus and INV2 holds.
     */
    private function closeAbandonedRental(int $userId, int $bikeId, int $openRentId): void
    {
        $lastStand = $this->historyRepository->findLastReturnStand($bikeId);
        $placeholderStand = $lastStand['standId'] ?? null;

        // `standId` is the typed fact; the legacy `parameter` mirrors it as a string. When the
        // bike has no prior return to borrow a placeholder from, both stay "unknown": standId
        // null and parameter '' (the deliberate empty sentinel — do not "fix" to '0').
        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            Action::FORCE_RETURN,
            (string)($placeholderStand ?? ''),
            $placeholderStand,
            $openRentId,
        );
    }
}
