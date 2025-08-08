<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\History\HistoryAction;
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
        HistoryAction $action,
        string $parameter
    ): void {
        $this->db->query(
            'INSERT INTO history (userId, bikeNum, action, parameter, time)
             VALUES (:userId, :bikeNum, :action, :parameter, :time)',
            [
                'userId' => $userId,
                'bikeNum' => $bikeNum,
                'action' => $action->value,
                'parameter' => $parameter,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function dailyStats(): array
    {
        $rent = HistoryAction::RENT->value;
        $return = HistoryAction::RETURN->value;
        $result = $this->db->query(
            sprintf(
                "SELECT
              DATE(time) AS day,
              SUM(CASE WHEN action = '%s' THEN 1 ELSE 0 END) AS rentCount,
              SUM(CASE WHEN action = '%s' THEN 1 ELSE 0 END) AS returnCount
            FROM history
            WHERE userId IS NOT NULL
              AND action IN ('%s','%s')
            GROUP BY day
            ORDER BY day DESC
            LIMIT 60",
                $rent,
                $return,
                $rent,
                $return
            )
        )->fetchAllAssoc();

        return $result;
    }

    public function userStats(int $year): array
    {
        $rent = HistoryAction::RENT->value;
        $return = HistoryAction::RETURN->value;
        $result = $this->db->query(
            sprintf(
                "SELECT
                users.userId,
                username,
                SUM(CASE WHEN action = '%s' THEN 1 ELSE 0 END) AS rentCount,
                SUM(CASE WHEN action = '%s' THEN 1 ELSE 0 END) AS returnCount,
                COUNT(action) AS totalActionCount
            FROM users
            LEFT JOIN history ON users.userId=history.userId
            WHERE history.userId IS NOT NULL
              AND YEAR(time) = :year
            GROUP BY username
            ORDER BY totalActionCount DESC",
                $rent,
                $return
            ),
            ['year' => $year]
        )->fetchAllAssoc();

        return $result;
    }

    public function findLastBikeRentByUser(int $bikeNumber, int $userId): ?array
    {
        $result = $this->db->query(
            sprintf(
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
              AND action = '%s'
            ORDER BY time DESC
            LIMIT 1",
                HistoryAction::RENT->value
            ),
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
            sprintf(
                "SELECT
              COUNT(*) AS rentCount
            FROM history
            WHERE userId = :userId
              AND action = '%s'
              AND time > :offsetTime",
                HistoryAction::RENT->value
            ),
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
            sprintf(
                "SELECT
              userId,
              bikeNum,
              time,
              action,
              parameter,
              standId
            FROM history
            WHERE action = '%s'
              AND parameter = :checkCode
              AND userId = :userId
            ORDER BY time DESC
            LIMIT 1",
                HistoryAction::PHONE_CONFIRM_REQUEST->value
            ),
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
            sprintf(
                "SELECT time, longitude, latitude
                 FROM `history`
                 LEFT JOIN stands ON stands.standid=history.parameter
                 WHERE bikenum = :bikeNumber
                   AND time > :startTime
                   AND action IN ('%s', '%s')
                 ORDER BY history.time DESC, history.id DESC",
                HistoryAction::RETURN->value,
                HistoryAction::FORCERETURN->value
            ),
            [
                'bikeNumber' => $bikeNumber,
                'startTime' => $startTime->format('Y-m-d H:i:s'),
            ]
        )->fetchAllAssoc();

        return $result;
    }
}
