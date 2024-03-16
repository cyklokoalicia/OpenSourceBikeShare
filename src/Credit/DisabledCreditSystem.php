<?php

namespace BikeShare\Credit;

class DisabledCreditSystem implements CreditSystemInterface
{
    public function getUserCredit($userid)
    {
        return 0;
    }

    public function getMinRequiredCredit()
    {
        return PHP_INT_MAX;
    }

    public function isEnoughCreditForRent($userid)
    {
        return true;
    }

    public function isEnabled()
    {
        return false;
    }

    public function getCreditCurrency()
    {
        return 0;
    }

    public function getRentalFee()
    {
        return 0;
    }

    public function getPriceCycle()
    {
        return 0;
    }

    public function getLongRentalFee()
    {
        return 0;
    }

    public function getLimitIncreaseFee()
    {
        return 0;
    }

    public function getViolationFee()
    {
        return 0;
    }
}
