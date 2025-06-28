<?php

declare(strict_types=1);

namespace BikeShare\Db;

use PDO;

class PdoDb implements DbInterface
{
    private PDO $conn;

    public function __construct(
        string $dsn,
        string $userName,
        string $password
    ) {
        $this->conn = new PDO(
            $dsn,
            $userName,
            $password,
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

    public function exec($query)
    {
        $result = $this->conn->exec($query);

        return $result;
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
