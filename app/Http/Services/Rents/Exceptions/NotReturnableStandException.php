<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

use BikeShare\Domain\Stand\Stand;

class NotReturnableStandException extends RentException
{
    /**
     * @var Stand
     */
    public $stand;


    /**
     * NotReturnableStandException constructor.
     *
     * @param Stand $stand
     */
    public function __construct(Stand $stand)
    {
        parent::__construct('Can return bike to this stand!');
        $this->stand = $stand;
    }
}
