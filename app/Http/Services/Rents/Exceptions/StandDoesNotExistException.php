<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

class StandDoesNotExistException extends \LogicException
{
    public $standName;

    public function __construct($standName)
    {
        $this->standName = $standName;
    }
}