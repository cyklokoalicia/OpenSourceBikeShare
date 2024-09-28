<?php

namespace BikeShare\Db;

use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(
        $dbserver,
        $dbuser,
        $dbpassword,
        $dbname,
        LoggerInterface $logger
    ) {
        $this->dbserver = $dbserver;
        $this->dbuser = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname = $dbname;
        $this->logger = $logger;

        //in future exception should be thrown
        //mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
        $this->conn = new \mysqli($this->dbserver, $this->dbuser, $this->dbpassword, $this->dbname);
        if (!$this->conn || $this->conn->connect_errno) {
            $this->logger->error(
                'DB connection error!',
                [
                    'error' => $this->conn->connect_error,
                    'errno' => $this->conn->connect_errno,
                ]
            );
            throw new \RuntimeException(
                'DB connection error!',
                !empty($this->conn->connect_errno) ? $this->conn->connect_errno : 0
            );
        }
        $this->conn->set_charset("utf8");
        $this->conn->autocommit(false);
    }

    public function query($query, $params = [])
    {
        $result = $this->conn->query($query);
        if (!$result) {
            $this->logger->error(
                'DB query error',
                [
                    'query' => $query,
                    'params' => $params,
                    'error' => $this->conn->get_connection_stats() ? $this->conn->error : 'unknown',
                    'errno' => $this->conn->get_connection_stats() ? $this->conn->errno : 'unknown',
                ]
            );
            $this->conn->rollback();

            throw new \RuntimeException('DB error in : ' . $query);
        }

        return new MysqliDbResult($result);
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
