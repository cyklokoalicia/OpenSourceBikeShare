<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use BikeShare\Enum\Action;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;

/**
 * The spec 0013 rental state machine: the single owner of a bike's rental transitions. Each
 * transition mutates the authoritative state in the `bikes` table **and** appends the matching
 * `history` event together, so the two can never drift — the invariant "a bike has `currentUser`
 * set ⟺ it has an open RENT in history" is enforced here, not by call-ordering across collaborators.
 *
 * The states are the `bikes` columns themselves: PARKED (on a stand) ↔ ON_TRIP (held by a user).
 * Transition *legality* (e.g. a non-forced rent of an already-rented bike) is guarded upstream by
 * the engine, which returns user-facing errors; this class performs already-legal transitions.
 *
 *   PARKED  --rent-->          ON_TRIP   assign to user + RENT (standId = origin)
 *   ON_TRIP --return-->        PARKED    park + RETURN (pairs the closed rent)
 *   PARKED  --force-return-->  PARKED    park + FORCERETURN relocation (pairs nothing)
 *   ON_TRIP --force-rent-->    ON_TRIP   synthetic close of the abandoned trip, then re-assign
 *   ON_TRIP --revert-->        PARKED    restore + REVERT and a synthetic RENT/RETURN pair
 */
class RentalStateMachine
{
    public function __construct(
        private readonly BikeRepository $bikeRepository,
        private readonly HistoryRepository $historyRepository,
    ) {
    }

    /**
     * Rent a bike to a user: assign it and log the RENT (origin = $originStand). A bike that is not
     * on a stand ($originStand === null) is being force-rented over an open trip — that abandoned
     * trip is closed first so it is not left dangling (INV2).
     */
    public function onRent(int $userId, int $bikeId, bool $force, string $newCode, ?int $originStand): void
    {
        if ($originStand === null) {
            $this->closeAbandonedTrip($userId, $bikeId);
        }

        $this->bikeRepository->assignToUser($bikeId, $userId, $newCode);
        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RENT : Action::RENT,
            $newCode,
            $originStand,
            null,
        );
    }

    /**
     * Return a bike to a stand: park it and log the RETURN. From ON_TRIP the RETURN closes the open
     * rent (paired to it); a forced return of an already-parked bike is a relocation (no pair, INV3).
     */
    public function onReturn(int $userId, int $bikeId, bool $force, int $standId): void
    {
        $closedRentId = $this->historyRepository->findOpenRentId($bikeId);

        $this->bikeRepository->returnToStand($bikeId, $standId, $force ? null : $userId);
        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RETURN : Action::RETURN,
            (string)$standId,
            $standId,
            $closedRentId,
        );
    }

    /**
     * Revert a rent: restore the bike to $standId/$code and log a REVERT marker (referencing the
     * cancelled rent) plus a synthetic RENT/RETURN pair recording the restore.
     */
    public function onRevert(int $userId, int $bikeId, int $standId, string $code): void
    {
        $cancelledRentId = $this->historyRepository->findOpenRentId($bikeId);

        $this->bikeRepository->revertToStand($bikeId, $standId, $code);
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
     * Close an open trip superseded by a forced hand-over with a synthetic FORCERETURN (paired to
     * the abandoned rent) so it is not left dangling (INV2). Its destination is the trip's own
     * origin — a zero-distance close, since the bike has no real stand while ON_TRIP; null only when
     * that origin is itself unknown, leaving standId null and parameter '' (the empty sentinel).
     */
    private function closeAbandonedTrip(int $userId, int $bikeId): void
    {
        $openRentId = $this->historyRepository->findOpenRentId($bikeId);
        if ($openRentId === null) {
            return;
        }

        $placeholderStand = $this->historyRepository->findStandIdById($openRentId);
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
