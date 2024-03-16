<?php

namespace BikeShare\Credit;

interface CreditSystemInterface
{
    /**
     * @return int
     */
    public function getUserCredit($userid);

    /**
     * @return int
     */
    public function getMinRequiredCredit();

    /**
     * @return bool
     */
    public function isEnoughCreditForRent($userid);

    /**
     * @return bool
     */
    public function isEnabled();

    /**
     * @return string
     */
    public function getCreditCurrency();

    /**
     * @return int
     */
    public function getRentalFee();

    /**
     * @return int
     */
    public function getPriceCycle();

    /**
     * @return int
     */
    public function getLongRentalFee();

    /**
     * @return int
     */
    public function getLimitIncreaseFee();

    /**
     * @return int
     */
    public function getViolationFee();
}
