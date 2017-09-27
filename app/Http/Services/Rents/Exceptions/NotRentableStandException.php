<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

use BikeShare\Domain\Stand\Stand;

class NotRentableStandException extends RentException
{

    /**
     * @var Stand
     */
    public $stand;


    /**
     * NotRentableStandException constructor.
     *
     * @param Stand $stand
     */
    public function __construct(Stand $stand)
    {
        parent::__construct('Can not make rent from this stand!');
        $this->stand = $stand;
    }
}
