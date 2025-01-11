<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class StandRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $result = $this->db->query(
            'SELECT
                    standId,
                    standName,
                    standDescription,
                    standPhoto,
                    serviceTag,
                    placeName,
                    longitude,
                    latitude
                FROM stands 
                ORDER BY standName'
        )->fetchAllAssoc();

        $stands = [];
        foreach ($result as $stand) {
            $stands[$stand['standId']] = $stand;
        }

        return $stands;
    }

    public function findItem(int $standId): ?array
    {
        $stand = $this->db->query(
            'SELECT
                    standId,
                    standName,
                    standDescription,
                    standPhoto,
                    serviceTag,
                    placeName,
                    longitude,
                    latitude
                FROM stands
                WHERE standId = :standId',
            [
                'standId' => $standId,
            ]
        )->fetchAssoc();

        return $stand;
    }

    public function findItemByName(string $standName): ?array
    {
        $stand = $this->db->query(
            'SELECT
                    standId,
                    standName,
                    standDescription,
                    standPhoto,
                    serviceTag,
                    placeName,
                    longitude,
                    latitude
                FROM stands
                WHERE standName = :standName LIMIT 1',
            [
                'standName' => $standName,
            ]
        )->fetchAssoc();

        return $stand;
    }

    public function findFreeStands(): array
    {
        $result = $this->db->query(
            "SELECT 
              count(bikes.bikeNum) as bikeCount,
              standName
              FROM stands
              LEFT JOIN bikes ON bikes.currentStand = stands.standId
              WHERE stands.serviceTag=0
              GROUP BY standName
              HAVING bikeCount=0
              ORDER BY 2"
        )->fetchAllAssoc();

        return $result;
    }
}
