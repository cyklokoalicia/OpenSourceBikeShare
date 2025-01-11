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
     * @deprecated use fetchAssoc
     */
    public function fetch_assoc()
    {
        return $this->fetchAssoc();
    }

    public function rowCount()
    {
        return $this->result ? (int)$this->result->rowCount() : 0;
    }

    /**
     * @TODO temporary solution, should be removed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->result, $name)) {
            return call_user_func_array([$this->result, $name], $arguments);
        } else {
            throw new \Exception("Method $name not found");
        }
    }

    /**
     * @TODO temporary solution, should be removed
     */
    public function __get($name)
    {
        if ($name === 'num_rows') {
            return $this->rowCount();
        } elseif (property_exists($this->result, $name)) {
            return $this->result->$name;
        } elseif (property_exists($this, $name)) {
            return $this->$name;
        } else {
            throw new \Exception("Property $name not found");
        }
    }
}
