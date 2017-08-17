<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

use BikeShare\Domain\Bike\Bike;

class BikeNotOnTopException extends RentException
{
    public $topBike;

    public function __construct(Bike $topBike)
    {
        $this->topBike = $topBike;
    }
}