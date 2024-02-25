<?php

namespace BikeShare\Db;

interface DbInterface
{
    public function connect();

    /**
     * @param string $query
     * @param array $params
     * @return mixed
     */
    public function query($query, $params = array());

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

    /**
     * TODO does it needed???
     * @param bool $mode
     * @return bool
     */
    public function setAutocommit($mode = true);

    /**
     * TODO does it needed???
     * @return bool
     */
    public function commit();

    /**
     * TODO does it needed???
     * @return bool
     */
    public function rollback();
}