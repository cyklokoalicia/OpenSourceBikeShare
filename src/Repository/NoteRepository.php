<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class NoteRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function findBikeNote(int $bikeNumber): array
    {
        $result = $this->db->query(
            'SELECT
                    noteId,
                    bikeNum,
                    userId,
                    note,
                    time
                FROM notes 
                WHERE bikeNum = ' . $bikeNumber . '
                    AND deleted is NULL
                ORDER BY time desc'
        )->fetchAllAssoc();

        return $result;
    }

    public function findStandNote(int $standId): array
    {
        $result = $this->db->query(
            'SELECT
                    noteId,
                    standId,
                    userId,
                    note,
                    time
                FROM notes 
                WHERE standId = ' . $standId . '
                    AND deleted is NULL
                ORDER BY time desc'
        )->fetchAllAssoc();

        return $result;
    }
}
