<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Symfony\Component\Clock\ClockInterface;

class HistoryRepository
{
    public function __construct(
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
    ) {
    }

    public function addItem(
        int $userId,
        int $bikeNum,
        string $action,
        string $parameter
    ): void {
        $this->db->query(
            'INSERT INTO history (userId, bikeNum, action, parameter, time)
             VALUES (:userId, :bikeNum, :action, :parameter, :time)',
            [
                'userId' => $userId,
                'bikeNum' => $bikeNum,
                'action' => $action,
                'parameter' => $parameter,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );
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
              AND YEAR(time) = :year
            GROUP BY username 
            ORDER BY totalActionCount DESC",
            ['year' => $year]
        )->fetchAllAssoc();

        return $result;
    }

    public function findLastBikeRentByUser(int $bikeNumber, int $userId): ?array
    {
        $result = $this->db->query(
            "SELECT
              userId,
              bikeNum,
              time,
              action,
              parameter,
              standId
            FROM history 
            WHERE bikeNum = :bikeNumber 
              AND userId = :userId 
              AND action = 'RENT' 
            ORDER BY time DESC 
            LIMIT 1",
            [
                'bikeNumber' => $bikeNumber,
                'userId' => $userId,
            ]
        )->fetchAssoc();

        return $result;
    }

    public function findRentCountByUser(int $userId, \DateTimeImmutable $offsetTime): int
    {
        $result = $this->db->query(
            "SELECT
              COUNT(*) AS rentCount
            FROM history 
            WHERE userId = :userId 
              AND action = 'RENT' 
              AND time > :offsetTime",
            [
                'userId' => $userId,
                'offsetTime' => $offsetTime->format('Y-m-d H:i:s'),
            ]
        )->fetchAssoc();

        return (int)($result['rentCount'] ?? 0);
    }

    public function findConfirmationRequest(string $checkCode, int $userId): ?array
    {
        $result = $this->db->query(
            "SELECT
              userId,
              bikeNum,
              time,
              action,
              parameter,
              standId
            FROM history 
            WHERE action = 'PHONE_CONFIRM_REQUEST' 
              AND parameter = :checkCode
              AND userId = :userId
            ORDER BY time DESC 
            LIMIT 1",
            [
                'checkCode' => $checkCode,
                'userId' => $userId,
            ]
        )->fetchAssoc();

        return $result;
    }

    public function findBikeTrip(int $bikeNumber, \DateTimeImmutable $startTime): array
    {
        $result = $this->db->query(
            "SELECT time, longitude, latitude
                 FROM `history`
                 LEFT JOIN stands ON stands.standid=history.parameter
                 WHERE bikenum = :bikeNumber
                   AND time > :startTime
                   AND action IN ('RETURN', 'FORCERETURN')
                 ORDER BY history.time DESC, history.id DESC",
            [
                'bikeNumber' => $bikeNumber,
                'startTime' => $startTime->format('Y-m-d H:i:s'),
            ]
        )->fetchAllAssoc();

        return $result;
    }
}
