<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

use BikeShare\Domain\Bike\BikeStatus;

class BikeNotRentedException extends ReturnException
{
    /**
     * @var BikeStatus
     */
    public $bikeStatus;

    public function __construct($status)
    {
        $this->bikeStatus = $status;
    }
}