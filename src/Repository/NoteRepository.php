<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class NoteRepository
{
    public function __construct(private DbInterface $db)
    {
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
                WHERE bikeNum = :bikeNumber
                    AND deleted is NULL
                ORDER BY time desc',
            [
                'bikeNumber' => $bikeNumber,
            ]
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
                WHERE standId = :standId
                    AND deleted is NULL
                ORDER BY time desc',
            [
                'standId' => $standId,
            ]
        )->fetchAllAssoc();

        return $result;
    }

    public function addNoteToStand(int $standId, int $userId, string $note): void
    {
        $this->db->query(
            'INSERT INTO notes (standId, userId, note, time)
                VALUES (:standId, :userId, :note, NOW())',
            [
                'standId' => $standId,
                'userId' => $userId,
                'note' => $note,
            ]
        );
    }

    public function addNoteToBike(int $bikeNumber, int $userId, string $note): void
    {
        $this->db->query(
            'INSERT INTO notes (bikeNum, userId, note, time)
                VALUES (:bikeNumber, :userId, :note, NOW())',
            [
                'bikeNumber' => $bikeNumber,
                'userId' => $userId,
                'note' => $note,
            ]
        );
    }

    public function deleteStandNote(int $standId, ?string $notePattern): int
    {
        $result = $this->db->query(
            'UPDATE notes
                SET deleted = NOW()
                WHERE standId = :standId
                    AND deleted IS NULL
                    AND note LIKE :notePattern',
            [
                'standId' => $standId,
                'notePattern' => '%' . $notePattern . '%',
            ]
        );

        return $result->rowCount();
    }

    public function deleteBikeNote(int $bikeNumber, ?string $notePattern): int
    {
        $result = $this->db->query(
            'UPDATE notes
                SET deleted = NOW()
                WHERE bikeNum = :bikeNumber
                    AND deleted IS NULL
                    AND note LIKE :notePattern',
            [
                'bikeNumber' => $bikeNumber,
                'notePattern' => '%' . $notePattern . '%',
            ]
        );

        return $result->rowCount();
    }

    public function addNoteToAllBikesOnStand(int $standId, int $userId, string $note): void
    {
        $this->db->query(
            "INSERT INTO notes (bikeNum, userId, note) 
                SELECT bikeNum, :userId, :note
                FROM bikes 
                WHERE currentStand = :standId",
            [
                'userId' => $userId,
                'note' => $note,
                'standId' => $standId,
            ]
        );
    }

    public function deleteNotesForAllBikesOnStand(int $standId, ?string $notePattern): int
    {
        $result = $this->db->query(
            "UPDATE notes 
            JOIN bikes ON notes.bikeNum = bikes.bikeNum
            SET deleted=now()
            WHERE bikes.currentStand=:standId
              AND note LIKE :note
              AND deleted IS NULL",
            [
                'standId' => $standId,
                'note' => '%' . $notePattern . '%',
            ]
        );

        return $result->rowCount();
    }
}
