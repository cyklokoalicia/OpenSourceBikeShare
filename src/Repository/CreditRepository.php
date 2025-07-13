<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class CreditRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function addCredits(int $userId, float $creditAmount): void
    {
        $this->db->query(
            "INSERT INTO credit (userId, credit)
             VALUES (:userId, :creditAmount)
              ON DUPLICATE KEY UPDATE credit = credit + :creditAmountUpdate",
            [
                'userId' => $userId,
                'creditAmount' => $creditAmount,
                'creditAmountUpdate' => $creditAmount,
            ]
        );
    }

    public function findItem(int $userId): float
    {
        $result = $this->db->query(
            "SELECT credit FROM credit WHERE userId = :userId",
            ['userId' => $userId]
        )->fetchAssoc();

        if (empty($result)) {
            return 0.0;
        }

        return (float)$result[0]['credit'];
    }
}
