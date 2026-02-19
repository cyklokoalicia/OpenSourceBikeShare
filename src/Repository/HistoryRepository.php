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

    /**
     * Finds the second-to-last RETURN or FORCE_RETURN action for a bike.
     * This is used to find where the bike was before the current return.
     * Returns array with 'standId' (parameter) and 'time' of the return, or null if not found.
     */
    public function findPreviousBikeReturn(int $bikeNumber): ?array
    {
        $result = $this->db->query(
            "SELECT
              parameter AS standId,
              time
            FROM history
            WHERE bikeNum = :bikeNumber
              AND action IN (:returnAction, :forceReturnAction)
            ORDER BY time DESC, id DESC
            LIMIT 1, 1",
            [
                'bikeNumber' => $bikeNumber,
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
            ]
        )->fetchAssoc();

        return $result ?: null;
    }

    public function findLastRentTime(int $bikeNum, int $userId): ?\DateTimeImmutable
    {
        $result = $this->db->query(
            "SELECT time FROM history
             WHERE bikeNum = :bikeNum AND userId = :userId
               AND action IN (:rentAction, :forceRentAction)
             ORDER BY time DESC, id DESC LIMIT 1",
            [
                'bikeNum' => $bikeNum,
                'userId' => $userId,
                'rentAction' => Action::RENT->value,
                'forceRentAction' => Action::FORCE_RENT->value,
            ]
        );

        if ($result->rowCount() !== 1) {
            return null;
        }

        return new \DateTimeImmutable($result->fetchAssoc()['time']);
    }

    public function findLastReturnTime(int $bikeNum, int $userId): ?\DateTimeImmutable
    {
        $result = $this->db->query(
            "SELECT time FROM history
             WHERE bikeNum = :bikeNum AND userId = :userId
               AND action IN (:returnAction, :forceReturnAction)
             ORDER BY time DESC, id DESC LIMIT 1",
            [
                'bikeNum' => $bikeNum,
                'userId' => $userId,
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
            ]
        );

        if ($result->rowCount() !== 1) {
            return null;
        }

        return new \DateTimeImmutable($result->fetchAssoc()['time']);
    }

    /**
     * @return array{standId: int, standName: string}|null
     */
    public function findLastReturnStand(int $bikeNum): ?array
    {
        $result = $this->db->query(
            "SELECT parameter, standName
             FROM stands
             LEFT JOIN history ON stands.standId = parameter
             WHERE bikeNum = :bikeNum
               AND action IN (:returnAction, :forceReturnAction)
             ORDER BY time DESC
             LIMIT 1",
            [
                'bikeNum' => $bikeNum,
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
            ]
        );

        if ($result->rowCount() !== 1) {
            return null;
        }

        $row = $result->fetchAssoc();

        return [
            'standId' => (int)$row['parameter'],
            'standName' => $row['standName'],
        ];
    }

    public function findLastRentCode(int $bikeNum): ?string
    {
        $result = $this->db->query(
            "SELECT parameter
             FROM history
             WHERE bikeNum = :bikeNum
               AND action IN (:rentAction, :forceRentAction)
             ORDER BY time DESC
             LIMIT 1",
            [
                'bikeNum' => $bikeNum,
                'rentAction' => Action::RENT->value,
                'forceRentAction' => Action::FORCE_RENT->value,
            ]
        );

        if ($result->rowCount() !== 1) {
            return null;
        }

        return str_pad($result->fetchAssoc()['parameter'], 4, '0', STR_PAD_LEFT);
    }

    public function findCreditHistoryByUser(int $userId, int $limit = 100, int $offset = 0): array
    {
        $result = $this->db->query(
            "SELECT
              id,
              time,
              action,
              parameter
            FROM history
            WHERE userId = :userId
              AND action = :creditChangeAction
            ORDER BY time DESC, id DESC
            LIMIT :limit OFFSET :offset",
            [
                'userId' => $userId,
                'creditChangeAction' => Action::CREDIT_CHANGE->value,
                'limit' => $limit,
                'offset' => $offset,
            ]
        )->fetchAllAssoc();

        return $result;
    }
}
