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
              HAVING bikeCount = 0
              ORDER BY 2"
        )->fetchAllAssoc();

        return $result;
    }

    public function findLastReturnedBikeOnStand(int $standId): ?int
    {
        $bikesOnStand = $this->db->query(
            "SELECT bikeNum FROM stands 
            LEFT JOIN bikes ON bikes.currentStand=stands.standId
            WHERE standId=:standId",
            ['standId' => $standId]
        )->fetchAllAssoc();

        if (count($bikesOnStand)) {
            $bikeQueryParams = [];
            foreach ($bikesOnStand as $num => $bike) {
                $bikeQueryParams[':bikeNum' . $num] = $bike['bikeNum'];
            }

            $result = $this->db->query(
                "SELECT bikeNum FROM history 
                WHERE action IN ('RETURN','FORCERETURN')
                    AND parameter=:standId 
                    AND bikeNum IN (" . implode(',', array_keys($bikeQueryParams)) . ")
                ORDER BY `time` DESC, id DESC
                LIMIT 1",
                array_merge(
                    ['standId' => $standId],
                    $bikeQueryParams,
                )
            )->fetchAssoc();

            return $result['bikeNum'] ?? null;
        }

        return null;
    }

    public function findBikesOnStand(int $standId): array
    {
        $result = $this->db->query(
            "SELECT bikeNum FROM bikes 
             WHERE currentStand=:standId
             ORDER BY bikeNum",
            ['standId' => $standId]
        )->fetchAllAssoc();

        return $result;
    }
}
