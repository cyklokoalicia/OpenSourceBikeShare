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

    public function addNoteToStand(int $standId, int $userId, string $note)
    {
        $this->db->query(
            'INSERT INTO notes (standId, userId, note, time)
                VALUES (' . $standId . ', ' . $userId . ', "' . $note . '", NOW())'
        );
    }

    public function addNoteToBike(int $bikeNumber, int $userId, string $note)
    {
        $this->db->query(
            'INSERT INTO notes (bikeNum, userId, note, time)
                VALUES (' . $bikeNumber . ', ' . $userId . ', "' . $note . '", NOW())'
        );
    }

    public function deleteStandNote(int $standId, string $notePattern): int
    {
        $this->db->query(
            'UPDATE notes
                SET deleted = NOW()
                WHERE standId = ' . $standId . '
                    AND deleted IS NULL
                    AND note LIKE "%' . $notePattern . '%"'
        );

        return $this->db->getAffectedRows();
    }

    public function deleteBikeNote(int $bikeNumber, string $notePattern): int
    {
        $this->db->query(
            'UPDATE notes
                SET deleted = NOW()
                WHERE bikeNum = ' . $bikeNumber . '
                    AND deleted IS NULL
                    AND note LIKE "%' . $notePattern . '%"'
        );

        return $this->db->getAffectedRows();
    }
}
