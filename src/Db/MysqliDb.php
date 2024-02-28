<?php

namespace BikeShare\Db;

class MysqliDb implements DbInterface
{
    /**
     * @var \mysqli
     */
    private $conn;
    /**
     * @var string
     */
    private $dbserver;
    /**
     * @var string
     */
    private $dbuser;
    /**
     * @var string
     */
    private $dbpassword;
    /**
     * @var string
     */
    private $dbname;
    /**
     * @var false
     */
    private $throwException;

    public function __construct($dbserver, $dbuser, $dbpassword, $dbname, $throwException = false)
    {
        $this->dbserver = $dbserver;
        $this->dbuser = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname = $dbname;
        $this->throwException = $throwException;
    }

    public function connect()
    {
        $this->conn = new \mysqli($this->dbserver, $this->dbuser, $this->dbpassword, $this->dbname);
        if (!$this->conn || $this->conn->connect_errno) {
            if ($this->throwException) {
                throw new \RuntimeException(
                    'DB connection error!',
                    !empty($this->conn->connect_errno) ? $this->conn->connect_errno : 0
                );
            } else {
                die(_('DB connection error!'));
            }
        }
        $this->conn->set_charset("utf8");
        $this->conn->autocommit(false);
    }

    public function query($query, $params = array())
    {
        $result = $this->conn->query($query);
        if (!$result) {
            $this->conn->rollback();
            if ($this->throwException) {
                throw new \RuntimeException('DB error in : ' . $query);
            } else {
                die(_('DB error') . ' ' . $this->conn->error . ' ' . _('in') . ': ' . $query);
            }
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->conn->affected_rows;
    }

    public function getLastInsertId()
    {
        return (int)$this->conn->insert_id;
    }

    public function escape($string)
    {
        return $this->conn->real_escape_string($string);
    }

    /**
     * TODO does it needed???
     * @param bool $mode
     * @return bool
     */
    public function setAutocommit($mode = true)
    {
        return $this->conn->autocommit($mode);
    }

    /**
     * TODO does it needed???
     * @return bool
     */
    public function commit()
    {
        return $this->conn->commit();
    }

    /**
     * TODO does it needed???
     * @return bool
     */
    public function rollback()
    {
        return $this->conn->rollback();
    }
}
