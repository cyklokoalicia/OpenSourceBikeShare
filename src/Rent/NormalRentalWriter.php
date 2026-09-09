<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use BikeShare\Db\DbInterface;
use BikeShare\Rent\DTO\RentalTransition;
use BikeShare\Rent\DTO\RentalWriteResult;
use BikeShare\Rent\Exception\RentalLedgerConflict;
use BikeShare\Repository\RentalLedgerRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Persistence core; deliberately not called by the existing web/SMS/QR rental systems yet.
 * Local effects must use the shared DB connection and throw on rejection. Notify only after this returns.
 */
class NormalRentalWriter
{
    public function __construct(
        private readonly DbInterface $db,
        private readonly RentalLedgerRepository $repository,
        private readonly NormalRentalPlanner $planner,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @param null|callable(RentalWriteResult): void $localEffects */
    public function rent(
        int $userId,
        int $bikeNum,
        string $newCode,
        ?callable $localEffects = null,
    ): RentalWriteResult {
        return $this->execute(
            $userId,
            $bikeNum,
            fn(array $bike, array $open) => $this->planner->rent($bike, $open, $userId, $newCode, $this->clock->now()),
            $localEffects,
        );
    }

    /** @param null|callable(RentalWriteResult): void $localEffects */
    public function returnBike(
        int $userId,
        int $bikeNum,
        int $standId,
        int $expectedRentId,
        ?callable $localEffects = null,
    ): RentalWriteResult {
        return $this->execute(
            $userId,
            $bikeNum,
            fn(array $bike, array $open) => $this->planner->returnBike(
                $bike,
                $open,
                $userId,
                $standId,
                $expectedRentId,
                $this->clock->now(),
            ),
            $localEffects,
        );
    }

    /**
     * @param callable(array, array): RentalTransition $plan
     * @param null|callable(RentalWriteResult): void $localEffects
     */
    private function execute(int $userId, int $bikeNum, callable $plan, ?callable $localEffects): RentalWriteResult
    {
        return $this->db->transactional(function () use ($userId, $bikeNum, $plan, $localEffects): RentalWriteResult {
            // All ledger operations use the same lock order, including different bikes held by one user.
            if (!$this->repository->lockUser($userId)) {
                throw new RentalLedgerConflict('rental_ledger_user_not_found');
            }
            $bike = $this->repository->lockBike($bikeNum);
            if ($bike === null) {
                throw new RentalLedgerConflict('rental_ledger_bike_not_found');
            }
            $open = $this->repository->findOpenRentalsForUpdate($bikeNum);
            $transition = $plan($bike, $open);
            if (!$this->repository->lockStand($transition->standId)) {
                throw new RentalLedgerConflict('rental_ledger_stand_not_found');
            }
            $eventId = $this->repository->write($transition);
            $result = new RentalWriteResult($transition->pairActionId ?? $eventId, $eventId, $transition);
            if ($localEffects !== null) {
                $localEffects($result);
            }

            return $result;
        });
    }
}
