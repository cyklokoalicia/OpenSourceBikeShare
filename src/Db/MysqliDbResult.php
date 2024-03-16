<?php

namespace BikeShare\Db;

use mysqli_result;

class MysqliDbResult implements DbResultInterface
{
    /**
     * @var mysqli_result|bool
     */
    private $result;

    public function __construct($result)
    {
        if (!($result instanceof mysqli_result) && !is_bool($result)) {
            throw new \Exception("Invalid result type");
        }
        $this->result = $result;
    }

    public function fetchAssoc()
    {
        return $this->result ? $this->result->fetch_assoc() : false;
    }

    public function fetchAllAssoc()
    {
        return $this->result ? $this->result->fetch_all(MYSQLI_ASSOC) : [];
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
        return $this->result ? (int)$this->result->num_rows : 0;
    }

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
