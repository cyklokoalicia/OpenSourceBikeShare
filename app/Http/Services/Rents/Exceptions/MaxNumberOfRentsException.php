<?php

namespace BikeShare\Http\Services\Rents\Exceptions;


class MaxNumberOfRentsException extends RentException
{
    public $userLimit;

    public $currentRents;

    public function __construct($userLimit, $currentRents)
    {
        $this->userLimit = $userLimit;
        $this->currentRents = $currentRents;
    }
}

