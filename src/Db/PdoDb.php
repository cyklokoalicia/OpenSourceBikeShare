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

            throw new \RuntimeException('DB error in : ' . $query);
        }

        return new PdoDbResult($result);
    }

    public function getAffectedRows(): int
    {
        throw new \RuntimeException('Not implemented');
    }

    public function getLastInsertId(): int
    {
        return (int)$this->conn->lastInsertId();
    }

    public function escape($string)
    {
        return $string;
    }
}