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

    public function dailyStats(): array
    {
        $result = $this->db->query(
            "SELECT 
              DATE(time) AS day,
              SUM(CASE WHEN action = 'RENT' THEN 1 ELSE 0 END) AS rentCount,
              SUM(CASE WHEN action = 'RETURN' THEN 1 ELSE 0 END) AS returnCount
            FROM history 
            WHERE userId IS NOT NULL 
              AND action IN ('RENT','RETURN') 
            GROUP BY day 
            ORDER BY day DESC
            LIMIT 60"
        )->fetchAllAssoc();

        return $result;
    }

    public function userStats(int $year): array
    {
        $result = $this->db->query(
            "SELECT
                users.userId,
                username,
                SUM(CASE WHEN action = 'RENT' THEN 1 ELSE 0 END) AS rentCount,
                SUM(CASE WHEN action = 'RETURN' THEN 1 ELSE 0 END) AS returnCount,
                COUNT(action) AS totalActionCount
            FROM users 
            LEFT JOIN history ON users.userId=history.userId 
            WHERE history.userId IS NOT NULL
              AND YEAR(time) = " . $year . "
            GROUP BY username 
            ORDER BY totalActionCount DESC"
        )->fetchAllAssoc();

        return $result;
    }
}
