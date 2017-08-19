<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

use BikeShare\Domain\User\User;

class BikeRentedByOtherUserException extends ReturnException
{
    /**
     * @var User
     */
    public $bikeOwner;

    public function __construct(User $bikeOwner)
    {
        $this->bikeOwner = $bikeOwner;
    }
}