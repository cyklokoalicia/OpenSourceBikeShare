<?php

namespace BikeShare\Authentication;

use BikeShare\Db\DbInterface;

class Auth
{
    /**
     * @phpcs:disable PSR12.Properties.ConstantVisibility
     */
    const SESSION_EXPIRATION = 86400 * 14; // 14 days to keep user logged in

    /**
     * @var DbInterface
     */
    private $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function getUserId()
    {
        if (isset($_COOKIE["loguserid"])) {
            return (int)$this->db->escape(trim($_COOKIE["loguserid"]));
        } else {
            return 0;
        }
    }

    public function getSessionId()
    {
        if (isset($_COOKIE["logsession"])) {
            return $this->db->escape(trim($_COOKIE["logsession"]));
        } else {
            return '';
        }
    }

    public function login($number, $password)
    {
        $number = $this->db->escape(trim($number));
        $password = $this->db->escape(trim($password));

        $result = $this->db->query(
            "SELECT userId FROM users WHERE number='$number' AND password=SHA2('$password',512)"
        );
        if ($result && $result->rowCount() == 1) {
            $row = $result->fetchAssoc();
            $userId = $row['userId'];
            $sessionId = hash('sha256', $userId . $number . time());
            $timeStamp = time() + self::SESSION_EXPIRATION;
            $this->db->query("DELETE FROM sessions WHERE userId='$userId'");
            $this->db->query(
                "INSERT INTO sessions SET userId='$userId',sessionId='$sessionId',timeStamp='$timeStamp'"
            );
            $this->db->commit();
            setcookie('loguserid', $userId, $timeStamp);
            setcookie('logsession', $sessionId, $timeStamp);
            header('HTTP/1.1 302 Found');
            header('Location: /');
            header('Connection: close');
        } else {
            header('HTTP/1.1 302 Found');
            header('Location: /?error=1');
            header('Connection: close');
        }
    }

    public function logout()
    {
        if ($this->isLoggedIn()) {
            $userid = $this->getUserId();
            $sessionId = $this->getSessionId();
            $this->db->query("DELETE FROM sessions WHERE userId='$userid' OR sessionId='$sessionId'");
            $this->db->commit();
        }
        setcookie("loguserid", "0", time() - 3600, "/");
        setcookie("logsession", "", time() - 3600, "/");
        header('HTTP/1.1 302 Found');
        header('Location: /');
        header('Connection: close');
    }

    public function refreshSession()
    {
        if (!$this->isLoggedIn()) {
            return;
        }

        $this->db->query("DELETE FROM sessions WHERE timeStamp<='" . time() . "'");
        $userid = $this->getUserId();
        $sessionId = $this->getSessionId();
        $result = $this->db->query(
            "SELECT sessionId FROM sessions WHERE userId='$userid' 
                                 AND sessionId='$sessionId' AND timeStamp>'" . time() . "'"
        );
        if ($result->rowCount() == 1) {
            $timestamp = time() + self::SESSION_EXPIRATION;
            $this->db->query(
                "UPDATE sessions SET timeStamp='$timestamp' WHERE userId='$userid' AND sessionId='$sessionId'"
            );
            $this->db->commit();
        } else {
            $this->logout();
        }
    }

    public function isLoggedIn()
    {
        $session = $this->getSessionId();

        if (!empty($session)) {
            $userid = $this->getUserId();
            $result = $this->db->query(
                "SELECT sessionId FROM sessions WHERE 
                           userId='$userid' AND sessionId='$session' AND timeStamp>'" . time() . "'"
            );
            if ($result && $result->rowCount() == 1) {
                return true;
            }
        }

        return false;
    }
}
