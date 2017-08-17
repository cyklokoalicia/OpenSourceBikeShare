<?php

namespace BikeShare\Http\Services\Rents\Exceptions;


class LowCreditException extends RentException
{
    public $requiredCredit;

    public $userCredit;

    public function __construct($userCredit, $requiredCredit)
    {
        $this->requiredCredit = $requiredCredit;
        $this->userCredit = $userCredit;
    }
}