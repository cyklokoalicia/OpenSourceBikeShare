<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use BikeShare\Enum\Action;
use BikeShare\Repository\HistoryRepository;

/**
 * Owns the rental side of the `history` ledger (spec 0013).
 *
 * Every rent/return/revert event is written here — never with bare addItem() calls in the
 * engine — so the per-bike event stream stays a valid PARKED↔ON_TRIP alternation and the
 * stored facts (standId, pairActionId) can never drift from reality. The invariants and the
 * forced-operation compensation (a synthetic FORCE_RETURN closing an open trip on a forced
 * hand-over) live in one place, mirroring how REVERT already synthesizes its rows.
 *
 * - RENT/FORCE_RENT: standId = origin, pairActionId = NULL. If the bike still has an open
 *   rental (forced hand-over), a synthetic closing FORCE_RETURN is written for it first.
 * - RETURN/FORCE_RETURN: standId = destination, pairActionId = the open rent it closes, or
 *   NULL when nothing is open (a forced relocation of a parked bike — not a trip).
 * - REVERT: REVERT + synthetic RENT + synthetic RETURN; the original open rental is cancelled.
 */
class RentalLedger
{
    public function __construct(
        private readonly HistoryRepository $historyRepository,
    ) {
    }

    /**
     * Record a rent. Returns the new RENT row id.
     *
     * If the bike already has an open rental (only reachable via a forced hand-over — the
     * normal rent path guards against it), a synthetic closing FORCE_RETURN is written first
     * so the previous trip is not left dangling (INV2).
     */
    public function recordRent(int $userId, int $bikeId, bool $force, string $code, ?int $originStand): int
    {
        $openRentId = $this->historyRepository->findOpenRentId($bikeId);
        if ($openRentId !== null) {
            $this->closeOpenRental($userId, $bikeId, $openRentId);
        }

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
     * Record a return. `pairActionId` links to the open rent this closes, or stays NULL when
     * nothing is open (a forced relocation of an already-parked bike — INV3).
     */
    public function recordReturn(int $userId, int $bikeId, bool $force, int $standId): void
    {
        $openRentId = $this->historyRepository->findOpenRentId($bikeId);

        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RETURN : Action::RETURN,
            (string)$standId,
            $standId,
            $openRentId,
        );
    }

    /**
     * Record a revert: a REVERT marker (pointing at the rental being cancelled) plus a synthetic
     * RENT+RETURN pair that restores the bike to its previous stand and code. The original
     * rental is treated as cancelled (dropped from trip accounting downstream).
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
     * Synthesize a closing FORCE_RETURN for an open rental that is being superseded by a forced
     * hand-over. The destination is the bike's last-known stand (the decided placeholder — the
     * bike has no real stand while on a trip), so the abandoned rental gets a terminus.
     */
    private function closeOpenRental(int $userId, int $bikeId, int $openRentId): void
    {
        $lastStand = $this->historyRepository->findLastReturnStand($bikeId);
        $placeholderStand = $lastStand['standId'] ?? null;

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
