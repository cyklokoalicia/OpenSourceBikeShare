<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

class BikeDoesNotExistException extends \LogicException
{
    public $bikeNumber;

    public function __construct($bikeNumber)
    {
        $this->bikeNumber = $bikeNumber;
    }
}