<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class RegistrationRepository
{
    public function __construct(private DbInterface $db)
    {
    }

    public function addItem(int $userId, string $userKey): void
    {
        $this->db->query(
            'INSERT INTO registration 
              (userId, userKey) 
              VALUES (:userId, :userKey)',
            [
                'userId' => $userId,
                'userKey' => $userKey
            ]
        );
    }

    public function findItem(string $userKey): ?array
    {
        $result = $this->db->query(
            'SELECT 
                userId,
                userKey 
              FROM registration 
              WHERE userKey=:userKey',
            [
                'userKey' => $userKey,
            ]
        )->fetchAssoc();

        return $result;
    }

    public function deleteItem(string $userKey): void
    {
        $this->db->query(
            'DELETE FROM registration WHERE userKey=:userKey',
            [
                'userKey' => $userKey,
            ]
        );
    }

    public function findItemByUserId($userId): ?array
    {
        $result = $this->db->query(
            'SELECT 
                userId,
                userKey 
              FROM registration 
              WHERE userId = :userId',
            [
                'userId' => $userId,
            ]
        )->fetchAssoc();

        return $result;
    }
}
