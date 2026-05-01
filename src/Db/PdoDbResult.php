<?php

declare(strict_types=1);

namespace BikeShare\Db;

use PDO;
use PDOStatement;

class PdoDbResult implements DbResultInterface
{
    /**
     * @var PDOStatement|bool
     */
    private $result;

    public function __construct($result)
    {
        if (!($result instanceof PDOStatement) && !is_bool($result)) {
            throw new \Exception("Invalid result type");
        }

        $this->result = $result;
    }

    public function fetchAssoc()
    {
        if ($this->result === false) {
            return false;
        } elseif ($this->result->rowCount() > 0) {
            return $this->result->fetch(PDO::FETCH_ASSOC);
        } elseif ($this->result->rowCount() === 0) {
            return null;
        } else {
            return false;
        }
    }

    public function fetchAllAssoc()
    {
        return $this->result ? $this->result->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @phpcs:disable Generic.NamingConventions.CamelCapsFunctionName
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName
     */
    #[\Deprecated(message: 'use fetchAssoc')]
    public function fetch_assoc()
    {
        return $this->fetchAssoc();
    }

    public function rowCount()
    {
        return $this->result ? (int)$this->result->rowCount() : 0;
    }
}
