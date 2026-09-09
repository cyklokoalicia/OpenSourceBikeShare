<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
use BikeShare\Rent\DTO\RentalTransition;

class RentalLedgerRepository
{
    public function __construct(private readonly DbInterface $db)
    {
    }

    public function lockUser(int $userId): bool
    {
        $this->assertTransaction();

        return $this->db->query(
            'SELECT userId FROM users WHERE userId = :userId FOR UPDATE',
            ['userId' => $userId],
        )->fetchAssoc() !== null;
    }

    public function lockBike(int $bikeNum): ?array
    {
        $this->assertTransaction();

        return $this->db->query(
            'SELECT bikeNum, currentUser, currentStand, currentCode FROM bikes WHERE bikeNum = :bikeNum FOR UPDATE',
            ['bikeNum' => $bikeNum],
        )->fetchAssoc();
    }

    public function lockStand(int $standId): bool
    {
        $this->assertTransaction();

        return $this->db->query(
            'SELECT standId FROM stands WHERE standId = :standId FOR UPDATE',
            ['standId' => $standId],
        )->fetchAssoc() !== null;
    }

    /** Call after locking the bike. This is a current read even under REPEATABLE READ. */
    public function findOpenRentalsForUpdate(int $bikeNum): array
    {
        $this->assertTransaction();

        return $this->db->query(
            "SELECT opening.* FROM history opening
             WHERE opening.bikeNum = :bikeNum AND opening.ledgerVersion = 1
               AND opening.action IN ('RENT','FORCERENT') AND opening.pairActionId IS NULL
               AND NOT EXISTS (
                 SELECT 1 FROM history terminal
                 WHERE terminal.pairActionId = opening.id AND terminal.ledgerVersion = 1
                   AND terminal.action IN ('RETURN','FORCERETURN','REVERT')
               )
             ORDER BY opening.id FOR UPDATE",
            ['bikeNum' => $bikeNum],
        )->fetchAllAssoc();
    }

    /** The caller holds the user, bike and stand locks and has validated the plan. */
    public function write(RentalTransition $transition): int
    {
        $this->assertTransaction();
        if ($transition->action === Action::RENT) {
            $result = $this->db->query(
                'UPDATE bikes SET currentUser = :userId, currentStand = NULL, currentCode = :code
                 WHERE bikeNum = :bikeNum AND currentUser IS NULL AND currentStand = :standId',
                [
                    'userId' => $transition->userId,
                    'code' => $transition->parameter,
                    'bikeNum' => $transition->bikeNum,
                    'standId' => $transition->standId,
                ],
            );
        } elseif ($transition->action === Action::RETURN) {
            $result = $this->db->query(
                'UPDATE bikes SET currentUser = NULL, currentStand = :standId
                 WHERE bikeNum = :bikeNum AND currentUser = :userId AND currentStand IS NULL',
                [
                    'standId' => $transition->standId,
                    'bikeNum' => $transition->bikeNum,
                    'userId' => $transition->userId,
                ],
            );
        } else {
            throw new \LogicException('Only ordinary rental transitions are supported.');
        }
        if ($result->rowCount() !== 1) {
            throw new \LogicException('Bike state changed outside the locked rental transition.');
        }
        $this->db->query(
            "INSERT INTO history
                (userId, bikeNum, action, parameter, time, standId, pairActionId,
                 ledgerVersion, recordOrigin, rentalKind, closeReason)
             VALUES (:userId, :bikeNum, :action, :parameter, :time, :standId, :pairActionId,
                     1, 'command', 'rental', :closeReason)",
            [
                'userId' => $transition->userId,
                'bikeNum' => $transition->bikeNum,
                'action' => $transition->action->value,
                'parameter' => $transition->parameter,
                'time' => $transition->occurredAt->format('Y-m-d H:i:s'),
                'standId' => $transition->standId,
                'pairActionId' => $transition->pairActionId,
                'closeReason' => $transition->action === Action::RETURN ? 'returned' : null,
            ],
        );

        return $this->db->getLastInsertId();
    }

    private function assertTransaction(): void
    {
        if (!$this->db->isTransactionActive()) {
            throw new \LogicException('Rental ledger access requires an active transaction.');
        }
    }
}
