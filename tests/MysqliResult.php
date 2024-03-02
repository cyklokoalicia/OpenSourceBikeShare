<?php

namespace Test\BikeShare;

/**
 * @deprecated just for testing
 * @phpcs:disable
 */
class MysqliResult
{
    /**
     * @var int
     */
    public $num_rows;
    /**
     * @var array
     */
    private $fetchResult;

    public function __construct($numRows, array $fetchResult = [])
    {
        $this->num_rows = $numRows;
        $this->fetchResult = $fetchResult;
    }

    public function fetch_assoc()
    {
        return array_shift($this->fetchResult);
    }
}
