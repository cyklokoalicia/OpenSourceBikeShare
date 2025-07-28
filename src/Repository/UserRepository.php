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
                city,
                mail,
                number,
                privileges,
                credit,
                userLimit,
                isNumberConfirmed
            FROM users 
            LEFT JOIN credit ON users.userId=credit.userId 
            LEFT JOIN limits ON users.userId=limits.userId 
            ORDER BY username'
        )->fetchAllAssoc();


        return $users;
    }

    public function findItem(int $userId): ?array
    {
        $user = $this->db->query(
            'SELECT 
                users.userId,
                username,
                city,
                mail,
                number,
                privileges,
                credit,
                userLimit,
                isNumberConfirmed
              FROM users 
              LEFT JOIN credit ON users.userId=credit.userId 
              LEFT JOIN limits ON users.userId=limits.userId 
              WHERE users.userId = :userId',
            [
                'userId' => $userId,
            ]
        )->fetchAssoc();

        return $user;
    }

    public function findItemByPhoneNumber(string $phoneNumber): ?array
    {
        $user = $this->db->query(
            'SELECT 
                users.userId,
                username,
                city,
                mail,
                number,
                privileges,
                credit,
                userLimit,
                isNumberConfirmed
              FROM users 
              LEFT JOIN credit ON users.userId=credit.userId 
              LEFT JOIN limits ON users.userId=limits.userId 
              WHERE users.number = :phoneNumber',
            [
                'phoneNumber' => $phoneNumber,
            ]
        )->fetchAssoc();

        return $user;
    }

    public function findItemByEmail(string $email): ?array
    {
        $user = $this->db->query(
            'SELECT 
                users.userId,
                username,
                city,
                mail,
                number,
                privileges,
                credit,
                userLimit,
                isNumberConfirmed
              FROM users 
              LEFT JOIN credit ON users.userId=credit.userId 
              LEFT JOIN limits ON users.userId=limits.userId 
              WHERE users.mail= :email',
            [
                'email' => $email,
            ]
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
    ): void {
        $this->db->query(
            'UPDATE users 
              SET username = :username, 
                  mail = :email,
                  number = :number,
                  privileges = :privileges 
              WHERE userId = :userId',
            [
                'userId' => $userId,
                'username' => $username,
                'email' => $email,
                'number' => $number,
                'privileges' => $privileges,
            ]
        );

        $this->db->query(
            'UPDATE limits 
              SET userLimit = :userLimit 
              WHERE userId = :userId',
            [
                'userId' => $userId,
                'userLimit' => $userLimit,
            ]
        );
    }

    public function updateUserLimit(int $userId, int $userLimit): void
    {
        $this->db->query(
            'INSERT INTO limits (userId, userLimit) 
                  VALUES (:userId, :userLimit) 
                  ON DUPLICATE KEY UPDATE userLimit = :userLimitUpdate',
            [
                'userId' => $userId,
                'userLimit' => $userLimit,
                'userLimitUpdate' => $userLimit,
            ]
        );
    }

    public function confirmUserNumber(int $userId): void
    {
        $this->db->query(
            'UPDATE users 
              SET isNumberConfirmed = 1 
              WHERE userId = :userId',
            [
                'userId' => $userId,
            ]
        );
    }

    public function updateUserCity(int $userId, string $city): void
    {
        $this->db->query(
            'UPDATE users 
              SET city = :city 
              WHERE userId = :userId',
            [
                'userId' => $userId,
                'city' => $city,
            ]
        );
    }
}
