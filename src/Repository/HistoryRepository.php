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
        string $parameter,
        ?int $standId = null,
        ?int $pairActionId = null
    ): int {
        $this->db->query(
            'INSERT INTO history (userId, bikeNum, action, parameter, standId, pairActionId, time)
             VALUES (:userId, :bikeNum, :action, :parameter, :standId, :pairActionId, :time)',
            [
                'userId' => $userId,
                'bikeNum' => $bikeNum,
                'action' => $action->value,
                'parameter' => $parameter,
                'standId' => $standId,
                'pairActionId' => $pairActionId,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );

        return (int)$this->db->getLastInsertId();
    }

    /**
     * Id of the bike's currently-open RENT/FORCERENT — a rent with no later RETURN/FORCERETURN —
     * or null if the bike is not on a trip. This is the "open rental" the rental state machine
     * (spec 0013) pairs each return against.
     */
    public function findOpenRentId(int $bikeNum): ?int
    {
        $result = $this->db->query(
            "SELECT rentEvent.id
             FROM history rentEvent
             WHERE rentEvent.bikeNum = :bikeNum
               AND rentEvent.action IN (:rentAction, :forceRentAction)
               AND NOT EXISTS (
                   SELECT 1 FROM history returnEvent
                   WHERE returnEvent.bikeNum = :bikeNumReturn
                     AND returnEvent.action IN (:returnAction, :forceReturnAction)
                     AND (returnEvent.time > rentEvent.time
                          OR (returnEvent.time = rentEvent.time AND returnEvent.id > rentEvent.id))
               )
             ORDER BY rentEvent.time DESC, rentEvent.id DESC
             LIMIT 1",
            [
                'bikeNum' => $bikeNum,
                'bikeNumReturn' => $bikeNum,
                'rentAction' => Action::RENT->value,
                'forceRentAction' => Action::FORCE_RENT->value,
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
            ]
        );

        $row = $result->fetchAssoc();

        return empty($row) ? null : (int)$row['id'];
    }

    /**
     * The standId stored on a single history row, or null if the row carries none.
     */
    public function findStandIdById(int $id): ?int
    {
        $result = $this->db->query(
            'SELECT standId FROM history WHERE id = :id',
            ['id' => $id]
        );

        $row = $result->fetchAssoc();

        return isset($row['standId']) ? (int)$row['standId'] : null;
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
        $yearStart = sprintf('%d-01-01 00:00:00', $year);
        $yearEnd = sprintf('%d-01-01 00:00:00', $year + 1);

        $result = $this->db->query(
            "SELECT
                users.userId,
                userName,
                SUM(CASE WHEN action = :rentActionSum THEN 1 ELSE 0 END) AS rentCount,
                SUM(CASE WHEN action = :returnActionSum THEN 1 ELSE 0 END) AS returnCount,
                COUNT(action) AS totalActionCount
            FROM history
            JOIN users ON users.userId=history.userId
            WHERE time >= :yearStart
              AND time < :yearEnd
            GROUP BY users.userId, users.userName
            ORDER BY totalActionCount DESC",
            [
                'yearStart' => $yearStart,
                'yearEnd' => $yearEnd,
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
            ORDER BY time DESC, id DESC
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
            ORDER BY time DESC, id DESC
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
             ORDER BY time DESC, history.id DESC
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
             ORDER BY time DESC, id DESC
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

    /**
     * Where and with what code a bike should be restored on revert: its last-known return stand
     * and last rent code. Null when either is missing (nothing to revert to).
     *
     * @return array{standId: int, standName: string, code: string}|null
     */
    public function findRevertTarget(int $bikeNum): ?array
    {
        $lastReturn = $this->findLastReturnStand($bikeNum);
        $code = $this->findLastRentCode($bikeNum);

        if ($lastReturn === null || $code === null) {
            return null;
        }

        return [
            'standId' => $lastReturn['standId'],
            'standName' => $lastReturn['standName'],
            'code' => $code,
        ];
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

    /**
     * Stand-scoped ride history (spec 0013): rentals originating at the stand and returns made
     * to it, newest first. Exact and index-backed via the populated `standId` — both RENT and
     * RETURN now carry the stand, so no correlated-subquery inference is needed.
     *
     * Rows written before spec 0013 (or never backfilled by app:backfill_history_standid) have
     * a NULL standId and so do not appear here.
     *
     * @return array<int, array{
     *     id: int, bikeNumber: int, action: string, time: string, userId: int, userName: string|null
     * }>
     */
    public function findStandHistory(int $standId, int $limit = 10): array
    {
        return $this->db->query(
            "SELECT
                h.id,
                h.bikeNum AS bikeNumber,
                h.action,
                h.time,
                h.userId,
                u.userName AS userName
            FROM history h
            LEFT JOIN users u ON u.userId = h.userId
            WHERE h.action IN (:rentAction, :forceRentAction, :returnAction, :forceReturnAction)
              AND h.standId = :standId
            ORDER BY h.time DESC, h.id DESC
            LIMIT :limit",
            [
                'rentAction' => Action::RENT->value,
                'forceRentAction' => Action::FORCE_RENT->value,
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
                'standId' => $standId,
                'limit' => $limit,
            ]
        )->fetchAllAssoc();
    }

    /**
     * Returns last N trips (rent then return) for a user.
     * Each trip has: rentTime, bikeNumber, returnTime, standName (to), fromStandName (from).
     *
     * @return array<int, array{
     *   rentTime: string,
     *   bikeNumber: int,
     *   returnTime: string|null,
     *   standName: string|null,
     *   fromStandName: string|null
     * }>
     */
    public function findUserTripHistory(int $userId, int $limit = 10): array
    {
        $rows = $this->db->query(
            "SELECT
              h.bikeNum AS bikeNumber,
              h.time AS rentTime,
              (SELECT r.time FROM history r
               WHERE r.userId = h.userId AND r.bikeNum = h.bikeNum
                 AND r.action IN (:returnAction, :forceReturnAction) AND r.time >= h.time
               ORDER BY r.time ASC, r.id ASC LIMIT 1) AS returnTime,
              (SELECT s.standName FROM history r
               LEFT JOIN stands s ON s.standId = r.parameter
               WHERE r.userId = h.userId AND r.bikeNum = h.bikeNum
                 AND r.action IN (:returnAction2, :forceReturnAction2) AND r.time >= h.time
               ORDER BY r.time ASC, r.id ASC LIMIT 1) AS standName,
              (SELECT s2.standName FROM history r2
               LEFT JOIN stands s2 ON s2.standId = r2.parameter
               WHERE r2.bikeNum = h.bikeNum
                 AND r2.action IN (:returnAction3, :forceReturnAction3) AND r2.time < h.time
               ORDER BY r2.time DESC, r2.id DESC LIMIT 1) AS fromStandName
            FROM history h
            WHERE h.userId = :userId AND h.action IN (:rentAction, :forceRentAction)
            ORDER BY h.time DESC, h.id DESC
            LIMIT :limit",
            [
                'userId' => $userId,
                'rentAction' => Action::RENT->value,
                'forceRentAction' => Action::FORCE_RENT->value,
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
                'returnAction2' => Action::RETURN->value,
                'forceReturnAction2' => Action::FORCE_RETURN->value,
                'returnAction3' => Action::RETURN->value,
                'forceReturnAction3' => Action::FORCE_RETURN->value,
                'limit' => $limit,
            ]
        )->fetchAllAssoc();

        return $rows;
    }
}
