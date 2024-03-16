<?php

namespace BikeShare\Credit;

use BikeShare\Db\DbInterface;

class CreditSystem implements CreditSystemInterface
{
    // 0 = no credit system, 1 = apply credit system rules and deductions
    private $isEnabled = false;
    // currency used for credit system
    private $creditCurrency = "€";
    // minimum credit required to allow any bike operations
    private $minBalanceCredit = 2;
    // rental fee (after $watches["freetime"])
    private $rentalFee = 2;
    // 0 = disabled, 1 = charge flat price $credit["rent"] every $watches["flatpricecycle"] minutes,
    // 2 = charge doubled price $credit["rent"] every $watches["doublepricecycle"] minutes
    private $priceCycle = 0;
    // long rental fee ($watches["longrental"] time)
    private $longRentalFee = 5;
    // credit needed to temporarily increase limit, applicable only when $limits["increase"]>0
    private $limitIncreaseFee = 10;
    // credit deduction for rule violations (applied by admins)
    private $violationFee = 5;

    /**
     * @var DbInterface
     */
    private $db;

    public function __construct(
        array $creditConfiguration,
        DbInterface $db
    ) {
        $this->parseConfiguration($creditConfiguration);
        $this->db = $db;
    }

    public function getUserCredit($userid)
    {
        $result = $this->db->query("SELECT credit FROM credit WHERE userId = '$userid'");
        if ($result->rowCount() == 0) {
            return 0;
        }

        return $result->fetchAssoc()['credit'];
    }

    public function getMinRequiredCredit()
    {
        return $this->minBalanceCredit + $this->rentalFee + $this->longRentalFee;
    }

    public function isEnoughCreditForRent($userid)
    {
        return $this->getUserCredit($userid) >= $this->getMinRequiredCredit();
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->isEnabled;
    }
    /**
     * @return string
     */
    public function getCreditCurrency()
    {
        return $this->creditCurrency;
    }

    /**
     * @return int
     */
    public function getRentalFee()
    {
        return $this->rentalFee;
    }

    /**
     * @return int
     */
    public function getPriceCycle()
    {
        return $this->priceCycle;
    }

    /**
     * @return int
     */
    public function getLongRentalFee()
    {
        return $this->longRentalFee;
    }

    /**
     * @return int
     */
    public function getLimitIncreaseFee()
    {
        return $this->limitIncreaseFee;
    }

    /**
     * @return int
     */
    public function getViolationFee()
    {
        return $this->violationFee;
    }

    /**
     * @TODO move to a CreditSystemFactory
     * @param array $creditConfiguration
     */
    private function parseConfiguration(array $creditConfiguration)
    {
        if (isset($creditConfiguration['enabled'])) {
            $this->isEnabled = (bool)$creditConfiguration['enabled'];
        }
        if (isset($creditConfiguration['currency'])) {
            $this->creditCurrency = (string)$creditConfiguration['currency'];
        }
        if (isset($creditConfiguration['min'])) {
            $this->minBalanceCredit = (int)$creditConfiguration['min'];
        }
        if (isset($creditConfiguration['rent'])) {
            $this->rentalFee = (int)$creditConfiguration['rent'];
        }
        if (isset($creditConfiguration['pricecycle'])) {
            $this->priceCycle = (int)$creditConfiguration['pricecycle'];
        }
        if (isset($creditConfiguration['longrental'])) {
            $this->longRentalFee = (int)$creditConfiguration['longrental'];
        }
        if (isset($creditConfiguration['limitincrease'])) {
            $this->limitIncreaseFee = (int)$creditConfiguration['limitincrease'];
        }
        if (isset($creditConfiguration['violation'])) {
            $this->violationFee = (int)$creditConfiguration['violation'];
        }
    }
}
