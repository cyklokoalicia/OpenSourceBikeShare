<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class RegistrationRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function addItem(int $userId, string $userKey): void
    {
        $this->db->query(
            'INSERT INTO registration 
              (userId, userKey) 
              VALUES (' . $userId . ', "' . $userKey . '")'
        );
    }

    public function findItem(string $userKey): ?array
    {
        $result = $this->db->query(
            'SELECT 
                userId,
                userKey 
              FROM registration 
              WHERE userKey="' . $userKey . '"'
        )->fetchAssoc();

        return $result;
    }

    public function deleteItem(string $key): void
    {
        $this->db->query(
            'DELETE FROM registration WHERE userKey="' . $key . '"'
        );
    }

    public function findItemByUserId($userId): ?array
    {
        $result = $this->db->query(
            'SELECT 
                userId,
                userKey 
              FROM registration 
              WHERE userId="' . $userId . '"'
        )->fetchAssoc();

        return $result;
    }
}