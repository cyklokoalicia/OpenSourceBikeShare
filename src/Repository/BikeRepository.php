<?php

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;

class BikeRepository
{
    public function __construct(private readonly DbInterface $db)
    {
    }

    public function findAll(): array
    {
        $bikes = $this->db->query(
            'SELECT
                    bikes.bikeNum,
                    currentUser as userId,
                    userName,
                    standName,
                    (standName REGEXP \'SERVIS$\') AS isServiceStand,
                    GROUP_CONCAT(note SEPARATOR \'; \') as notes,
                    null as rentTime
                FROM bikes
                         LEFT JOIN users ON bikes.currentUser=users.userId
                         LEFT JOIN stands ON bikes.currentStand=stands.standId
                         LEFT JOIN notes ON bikes.bikeNum=notes.bikeNum AND notes.deleted IS NULL
                GROUP BY bikeNum
                ORDER BY bikeNum'
        )->fetchAllAssoc();

        foreach ($bikes as &$bike) {
            /**
             * Should be optimized to one query
             */
            if (!empty($bike['userName'])) {
                $historyInfo = $this->db->query(
                    'SELECT time
                     FROM history
                     WHERE bikeNum = :bikeNumber
                         AND userId = :userId
                         AND action = :action
                     ORDER BY time DESC
                     LIMIT 1',
                    [
                        'bikeNumber' => $bike['bikeNum'],
                        'userId' => $bike['userId'],
                        'action' => Action::RENT->value,
                    ]
                )->fetchAssoc();

                $bike['rentTime'] = date('d/m H:i', strtotime((string) $historyInfo['time']));
            }
        }

        unset($bike);

        return $bikes;
    }

    public function findItem(int $bikeNumber): array
    {
        $bike = $this->db->query(
            'SELECT
                    bikes.bikeNum,
                    currentUser as userId,
                    userName,
                    standName,
                    (standName REGEXP \'SERVIS$\') AS isServiceStand,
                    GROUP_CONCAT(note SEPARATOR \'; \') as notes,
                    null as rentTime
                FROM bikes
                         LEFT JOIN users ON bikes.currentUser=users.userId
                         LEFT JOIN stands ON bikes.currentStand=stands.standId
                         LEFT JOIN notes ON bikes.bikeNum=notes.bikeNum AND notes.deleted IS NULL
                WHERE bikes.bikeNum = :bikeNumber
                GROUP BY bikeNum',
            [
                'bikeNumber' => $bikeNumber,
            ]
        )->fetchAssoc();

        /**
         * Should be optimized to one query
         */
        if (!empty($bike['userName'])) {
            $historyInfo = $this->db->query(
                'SELECT time
                     FROM history
                     WHERE bikeNum = :bikeNumber
                         AND userId = :userId
                         AND action IN (:rentAction, :forceRentAction)
                     ORDER BY time DESC
                     LIMIT 1',
                [
                    'bikeNumber' => $bike['bikeNum'],
                    'userId' => $bike['userId'],
                    'rentAction' => Action::RENT->value,
                    'forceRentAction' => Action::FORCE_RENT->value,
                ]
            )->fetchAssoc();

            $bike['rentTime'] = date('d/m H:i', strtotime((string) $historyInfo['time']));
        }

        return $bike;
    }

    public function findItemLastUsage(int $bikeNumber): array
    {
        $notes = $this->db->query(
            'SELECT 
                 GROUP_CONCAT(note ORDER BY time SEPARATOR \'; \') as notes
             FROM notes 
             WHERE bikeNum = :bikeNumber
                 AND deleted IS NULL 
             GROUP BY bikeNum',
            [
                'bikeNumber' => $bikeNumber,
            ]
        )->fetchAssoc();

        $history = $this->db->query(
            'SELECT
                 userName,
                 parameter,
                 standName,
                 action,
                 time
             FROM history
             JOIN users ON history.userid=users.userid 
             LEFT JOIN stands ON stands.standid=history.parameter 
             WHERE bikenum = :bikeNumber
               AND action NOT LIKE \'%CREDIT%\'
             ORDER BY time DESC, id DESC
             LIMIT 10',
            [
                'bikeNumber' => $bikeNumber,
            ]
        )->fetchAllAssoc();

        $result = [];
        $result['notes'] = $notes['notes'] ?? '';
        $result['history'] = [];
        foreach ($history as $row) {
            $historyItem = [];
            $historyItem['time'] = $row['time'];
            $historyItem['action'] = $row['action'];
            $historyItem['userName'] = $row['userName'];

            if (!is_null($row['standName'])) {
                $historyItem['standName'] = $row['standName'];
                if (strpos((string) $row['parameter'], '|')) {
                    $revertCode = explode('|', (string) $row['parameter']);
                    $revertCode = $revertCode[1];
                }

                if ($row['action'] == Action::REVERT->value) {
                    $historyItem['parameter'] = str_pad($revertCode ?? '', 4, '0', STR_PAD_LEFT);
                }
            } else {
                $historyItem['parameter'] = str_pad((string) $row['parameter'], 4, '0', STR_PAD_LEFT);
            }

            $result['history'][] = $historyItem;
        }

        return $result;
    }

    public function findRentedBikes(): array
    {
        $result = $this->db->query(
            "SELECT
                bikes.bikeNum,
                users.userId,
                users.number,
                users.userName
             FROM bikes
             JOIN users ON bikes.currentUser=users.userId
             WHERE currentStand IS NULL"
        )->fetchAllAssoc();

        return $result;
    }

    public function findFreeBikes(): array
    {
        $result = $this->db->query(
            "SELECT 
              count(*) as bikeCount,
              standName
            FROM bikes
            JOIN stands on bikes.currentStand=stands.standId 
            WHERE stands.serviceTag=0
            GROUP BY standName 
            HAVING bikeCount > 0 
            ORDER BY 2"
        )->fetchAllAssoc();

        return $result;
    }

    public function findBikeCurrentUsage(int $bikeNumber): array
    {
        $result = $this->db->query(
            "SELECT
                bikes.bikeNum,
                users.number,
                users.userName,
                stands.standName 
            FROM bikes 
                LEFT JOIN users on bikes.currentUser=users.userID 
                LEFT JOIN stands on bikes.currentStand=stands.standId 
            where bikeNum = :bikeNumber",
            [
                'bikeNumber' => $bikeNumber,
            ]
        )->fetchAssoc();

        return $result;
    }

    public function findRentedBikesByUserId(int $userId): array
    {
        $bikes = $this->db->query(
            "SELECT
              bikeNum,
              currentCode
            FROM bikes WHERE currentUser = :userId
            ORDER BY bikeNum",
            [
                'userId' => $userId,
            ]
        )->fetchAllAssoc();

        foreach ($bikes as &$bike) {
            $bike['currentCode'] = str_pad((string) $bike['currentCode'], 4, '0', STR_PAD_LEFT);
            $historyInfo = $this->db->query(
                "SELECT TIMESTAMPDIFF(SECOND, time, NOW()) as rentedSeconds, parameter
                FROM history
                WHERE bikeNum = :bikeNumber
                    AND action IN (:rentAction, :forceRentAction)
                ORDER BY time DESC, id DESC LIMIT 2",
                [
                    'bikeNumber' => $bike['bikeNum'],
                    'rentAction' => Action::RENT->value,
                    'forceRentAction' => Action::FORCE_RENT->value,
                ]
            )->fetchAllAssoc();
            foreach ($historyInfo as $index => $row) {
                if ($index === 0) {
                    $bike['rentedSeconds'] = $row['rentedSeconds'];
                } else {
                    $bike['oldCode'] = str_pad((string) $row['parameter'], 4, '0', STR_PAD_LEFT);
                }
            }
        }

        unset($bike);

        return $bikes;
    }
}
