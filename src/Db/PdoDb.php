<?php

declare(strict_types=1);

namespace BikeShare\Db;

use PDO;

class PdoDb implements DbInterface
{
    private readonly PDO $conn;

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

    public function query(string $query, array $params = []): DbResultInterface
    {
        $result = $this->conn->prepare($query);
        $result->execute($params);

        return new PdoDbResult($result);
    }

    public function exec(string $query): int|bool
    {
        $result = $this->conn->exec($query);

        return $result;
    }

    public function getLastInsertId(): int
    {
        return (int)$this->conn->lastInsertId();
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        if ($this->isTransactionActive()) {
            throw new \LogicException('Nested transactions are not supported.');
        }

        $this->conn->beginTransaction();
        try {
            $result = $operation();
            $this->conn->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->isTransactionActive()) {
                $this->conn->rollBack();
            }

            throw $exception;
        }
    }

    /** @phpstan-impure */
    public function isTransactionActive(): bool
    {
        return $this->conn->inTransaction();
    }
}
