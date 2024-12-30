<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class UserRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function findAll(): array
    {
        $users = $this->db->query(
            'SELECT 
                users.userId,
                username,
                mail,
                number,
                privileges,
                credit,
                userLimit 
            FROM users 
            LEFT JOIN credit ON users.userId=credit.userId 
            LEFT JOIN limits ON users.userId=limits.userId 
            ORDER BY username'
        )->fetchAllAssoc();


        return $users;
    }

    public function findItem(int $userId): array
    {
        $user = $this->db->query(
            'SELECT 
                users.userId,
                username,
                mail,
                number,
                privileges,
                credit,
                userLimit 
              FROM users 
              LEFT JOIN credit ON users.userId=credit.userId 
              LEFT JOIN limits ON users.userId=limits.userId 
              WHERE users.userId=' . $userId
        )->fetchAssoc();

        return $user;
    }

    public function updateItem(
        int $userId,
        string $username,
        string $email,
        string $number,
        int $privileges,
        int $userLimit
    ): void
    {
        $this->db->query(
            'UPDATE users 
              SET username="' . $username . '", mail="' . $email . '", number="' . $number . '", privileges=' . $privileges . ' 
              WHERE userId=' . $userId
        );

        $this->db->query(
            'UPDATE limits 
              SET userLimit=' . $userLimit . ' 
              WHERE userId=' . $userId
        );
    }
}
