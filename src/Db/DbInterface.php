<?php

namespace BikeShare\Db;

interface DbInterface
{
    /**
     * @param string $query
     * @param array $params
     * @return DbResultInterface
     */
    public function query($query, $params = []);

    /**
     * @param $string
     * @return string
     */
    public function escape($string);

    /**
     * @return int
     */
    public function getAffectedRows();

    /**
     * @return int
     */
    public function getLastInsertId();
}
