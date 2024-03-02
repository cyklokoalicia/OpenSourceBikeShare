<?php

namespace BikeShare\User;

use BikeShare\Db\DbInterface;

class User
{
    /**
     * @var DbInterface
     */
    private $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function findUserIdByNumber($number)
    {
        $result = $this->db->query("SELECT userId FROM users WHERE number='$number'");
        if ($result->num_rows == 1) {
            return $result->fetch_assoc()["userId"];
        }

        return null;
    }

    public function findPhoneNumber($userId)
    {
        $result = $this->db->query("SELECT number FROM users WHERE userId='$userId'");
        if ($result->num_rows == 1) {
            return $result->fetch_assoc()["number"];
        }

        return null;
    }

    public function findCity($userId)
    {
        $result = $this->db->query("SELECT city FROM users WHERE userId='$userId'");
        if ($result->num_rows == 1) {
            return $result->fetch_assoc()["city"];
        }

        return null;
    }

    public function findUserName($userId)
    {
        $result = $this->db->query("SELECT userName FROM users WHERE userId='$userId'");
        if ($result->num_rows == 1) {
            return $result->fetch_assoc()["userName"];
        }

        return null;
    }

    public function findPrivileges($userId)
    {
        $result = $this->db->query("SELECT privileges FROM users WHERE userId='$userId'");
        if ($result->num_rows == 1) {
            return $result->fetch_assoc()["privileges"];
        }

        return null;
    }
}
