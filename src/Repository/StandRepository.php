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
                FROM stands'
        )->fetchAllAssoc();

        $stands = [];
        foreach ($result as $stand) {
            $stands[$stand['standId']] = $stand;
        }

        return $stands;
    }

    public function findItem(int $standId): array
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
                WHERE standId = ' . $standId . ''
        )->fetchAllAssoc();

        return $stand;
    }
}
