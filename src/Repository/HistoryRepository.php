<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
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
        Action $action,
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

        $result = $this->db->query(
            "SELECT
              DATE(time) AS day,
              SUM(CASE WHEN action = :rentActionSum THEN 1 ELSE 0 END) AS rentCount,
              SUM(CASE WHEN action = :returnActionSum THEN 1 ELSE 0 END) AS returnCount
            FROM history
            WHERE userId IS NOT NULL
              AND action IN (:rentAction, :returnAction)
            GROUP BY day
            ORDER BY day DESC
            LIMIT 60",
            [
                'rentActionSum' => Action::RENT->value,
                'returnActionSum' => Action::RETURN->value,
                'rentAction' => Action::RENT->value,
                'returnAction' => Action::RETURN->value,
            ]
        )->fetchAllAssoc();

        return $result;
    }

    public function userStats(int $year): array
    {
        $result = $this->db->query(
            "SELECT
                users.userId,
                username,
                SUM(CASE WHEN action = :rentActionSum THEN 1 ELSE 0 END) AS rentCount,
                SUM(CASE WHEN action = :returnActionSum THEN 1 ELSE 0 END) AS returnCount,
                COUNT(action) AS totalActionCount
            FROM users
            LEFT JOIN history ON users.userId=history.userId
            WHERE history.userId IS NOT NULL
              AND YEAR(time) = :year
            GROUP BY username
            ORDER BY totalActionCount DESC",
            [
                'year' => $year,
                'rentActionSum' => Action::RENT->value,
                'returnActionSum' => Action::RETURN->value,
            ]
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
              AND action = :rentAction
            ORDER BY time DESC
            LIMIT 1",
            [
                'bikeNumber' => $bikeNumber,
                'userId' => $userId,
                'rentAction' => Action::RENT->value,
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
              AND action = :rentAction
              AND time > :offsetTime",
            [
                'userId' => $userId,
                'offsetTime' => $offsetTime->format('Y-m-d H:i:s'),
                'rentAction' => Action::RENT->value,
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
            WHERE action = :phoneConfirmRequestAction
              AND parameter = :checkCode
              AND userId = :userId
            ORDER BY time DESC
            LIMIT 1",
            [
                'checkCode' => $checkCode,
                'userId' => $userId,
                'phoneConfirmRequestAction' => Action::PHONE_CONFIRM_REQUEST->value,
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
                   AND action IN (:returnAction, :forceReturnAction)
                 ORDER BY history.time DESC, history.id DESC",
            [
                'bikeNumber' => $bikeNumber,
                'startTime' => $startTime->format('Y-m-d H:i:s'),
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
            ]
        )->fetchAllAssoc();

        return $result;
    }
}
