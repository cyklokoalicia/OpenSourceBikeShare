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
        $result = $this->conn->prepare($query);
        $result->execute($params);

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
