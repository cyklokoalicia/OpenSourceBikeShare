<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class HistoryRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function addItem(
        int $userId,
        int $bikeNum,
        string $action,
        string $parameter
    ): void {
        $userId = $this->db->escape($userId);
        $bikeNum = $this->db->escape($bikeNum);
        $action = $this->db->escape($action);
        $parameter = $this->db->escape($parameter);

        $this->db->query("
            INSERT INTO history (userId, bikeNum, action, parameter)
            VALUES ($userId, $bikeNum, '$action', '$parameter')
        ");
    }
}
