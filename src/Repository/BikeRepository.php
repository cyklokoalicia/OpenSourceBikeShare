<?php

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class BikeRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
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
                $historyInfo = $this->db->query('
                    SELECT time 
                    FROM history 
                    WHERE bikeNum=' . $bike['bikeNum'] . ' 
                        AND userId=' . $bike['userId'] . ' 
                        AND action=\'RENT\' 
                    ORDER BY time DESC 
                    LIMIT 1
                ')->fetchAssoc();

                $bike['rentTime'] = date('d/m H:i', strtotime($historyInfo['time']));
            }
        }
        unset($bike);

        return $bikes;
    }

    public function findItem(int $bikeNumber): array
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
                WHERE bikes.bikeNum = ' . $bikeNumber . '
                GROUP BY bikeNum'
        )->fetchAllAssoc();

        foreach ($bikes as &$bike) {
            /**
             * Should be optimized to one query
             */
            if (!empty($bike['userName'])) {
                $historyInfo = $this->db->query('
                    SELECT time 
                    FROM history 
                    WHERE bikeNum=' . $bike['bikeNum'] . ' 
                        AND userId=' . $bike['userId'] . ' 
                        AND action=\'RENT\' 
                    ORDER BY time DESC 
                    LIMIT 1
                ')->fetchAssoc();

                $bike['rentTime'] = date('d/m H:i', strtotime($historyInfo['time']));
            }
        }
        unset($bike);

        return $bikes;
    }

    public function findItemLastUsage(int $bikeNumber): array
    {
        $notes = $this->db->query('
            SELECT 
                GROUP_CONCAT(note ORDER BY time SEPARATOR \'; \') as notes
            FROM notes 
            WHERE bikeNum=' . $bikeNumber . ' 
                AND deleted IS NULL 
            GROUP BY bikeNum
        ')->fetchAssoc();

        $history = $this->db->query('
            SELECT
                userName,
                parameter,
                standName,
                action,
                time
            FROM history
            JOIN users ON history.userid=users.userid 
            LEFT JOIN stands ON stands.standid=history.parameter 
            WHERE bikenum= ' . $bikeNumber . ' 
              AND action NOT LIKE \'%CREDIT%\'
            ORDER BY time DESC 
            LIMIT 10
        ')->fetchAllAssoc();

        $result = [];
        $result['notes'] = $notes['notes'] ?? '';
        $result['history'] = [];
        foreach ($history as $row) {
            $historyItem = [];
            $historyItem['time'] = date('d/m H:i', strtotime($row['time']));
            $historyItem['action'] = $row['action'];

            if ($row['standName'] != null) {
                $historyItem['standName'] = $row['standName'];
                if (strpos($row['parameter'], '|')) {
                    $revertCode = explode('|', $row['parameter']);
                    $revertCode = $revertCode[1];
                }
                if ($row['action'] == 'REVERT') {
                    $historyItem['parameter'] = str_pad($revertCode, 4, '0', STR_PAD_LEFT);
                }
            } else {
                $historyItem['userName'] = $row['userName'];
                $historyItem['parameter'] = str_pad($row['parameter'], 4, '0', STR_PAD_LEFT);
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
            GROUP BY placeName 
            HAVING bikeCount>0 
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
            where bikeNum=$bikeNumber"
        )->fetchAssoc();

        return $result;
    }
}
