<?php

namespace BikeShare\User;

use BikeShare\Db\DbInterface;

class User
{
    public function __construct(private readonly DbInterface $db)
    {
    }

    public function findUserIdByNumber($number)
    {
        $result = $this->db->query(
            'SELECT userId FROM users WHERE number = :number',
            ['number' => $number]
        );
        if ($result->rowCount() == 1) {
            return $result->fetchAssoc()["userId"];
        }

        return null;
    }

    public function findPhoneNumber($userId)
    {
        $result = $this->db->query(
            'SELECT number FROM users WHERE userId = :userId',
            ['userId' => $userId]
        );
        if ($result->rowCount() == 1) {
            return $result->fetchAssoc()["number"];
        }

        return null;
    }

    public function findCity($userId)
    {
        $result = $this->db->query(
            'SELECT city FROM users WHERE userId = :userId',
            ['userId' => $userId]
        );
        if ($result->rowCount() == 1) {
            return $result->fetchAssoc()["city"];
        }

        return null;
    }

    public function findUserName($userId)
    {
        $result = $this->db->query(
            'SELECT userName FROM users WHERE userId = :userId',
            ['userId' => $userId]
        );
        if ($result->rowCount() == 1) {
            return $result->fetchAssoc()["userName"];
        }

        return null;
    }

    public function findPrivileges($userId)
    {
        $result = $this->db->query(
            'SELECT privileges FROM users WHERE userId = :userId',
            ['userId' => $userId]
        );
        if ($result->rowCount() == 1) {
            return $result->fetchAssoc()["privileges"];
        }

        return null;
    }
}
