<?php

declare(strict_types=1);

namespace BikeShare\Db;

use PDO;
use Psr\Log\LoggerInterface;

class PdoDb implements DbInterface
{
    private PDO $conn;
    private ?LoggerInterface $logger;

    public function __construct(
        string $dsn,
        string $dbuser,
        string $dbpassword,
        ?LoggerInterface $logger
    ) {
        $this->logger = $logger;

        $this->conn = new PDO(
            $dsn,
            $dbuser,
            $dbpassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_AUTOCOMMIT => false,
            ]
        );
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
                    'error' => $this->conn->errorInfo() ? $this->conn->errorInfo() : 'unknown',
                    'errno' => $this->conn->errorCode() ? $this->conn->errorCode() : 'unknown',
                ]
            );
            $this->conn->rollback();

            throw new \RuntimeException('DB error in : ' . $query);
        }

        return new PdoDbResult($result);
    }

    /**
     * @return int
     */
    public function getAffectedRows(): int
    {
        return $this->conn->affected_rows;
    }

    public function getLastInsertId(): int
    {
        return (int)$this->conn->lastInsertId();
    }

    public function escape($string)
    {
        return $string;
    }

    /**
     * TODO does it needed???
     * @param bool $mode
     * @return bool
     */
    public function setAutocommit($mode = true)
    {
        return $this->conn->setAttribute(
            PDO::ATTR_AUTOCOMMIT,
            $mode
        );
    }

    /**
     * TODO does it needed???
     * @return bool
     */
    public function commit()
    {
        if ($this->conn->inTransaction()) {
            return $this->conn->commit();
        }

        return true;
    }

    /**
     * TODO does it needed???
     * @return bool
     */
    public function rollback()
    {
        if (!$this->conn->inTransaction()) {
            return true;
        }

        return $this->conn->rollback();
    }
}
