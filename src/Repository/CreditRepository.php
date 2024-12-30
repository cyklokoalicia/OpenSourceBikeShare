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

    public function addItem(int $userId, float $creditAmount): void
    {
        $userId = $this->db->escape($userId);
        $creditAmount = $this->db->escape($creditAmount);
        $this->db->query("UPDATE credit SET credit=credit+" . $creditAmount . " WHERE userId=" . $userId);
    }
}
