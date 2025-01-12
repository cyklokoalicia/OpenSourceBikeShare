<?php

declare(strict_types=1);

namespace BikeShare\Credit;

use BikeShare\Db\DbInterface;

class CreditSystem implements CreditSystemInterface
{
    // false = no credit system and Exceptions will be thrown
    // true = apply credit system rules and deductions
    private bool $isEnabled;
    // currency used for credit system
    private string $creditCurrency;
    // minimum credit required to allow any bike operations
    private float $minBalanceCredit;
    // rental fee (after $watches["freetime"])
    private float $rentalFee;
    // 0 = disabled,
    // 1 = charge flat price $credit["rent"] every $watches["flatpricecycle"] minutes,
    // 2 = charge doubled price $credit["rent"] every $watches["doublepricecycle"] minutes
    private int $priceCycle;
    // long rental fee ($watches["longrental"] time)
    private float $longRentalFee;
    // credit needed to temporarily increase limit, applicable only when $limits["increase"]>0
    private float $limitIncreaseFee;
    // credit deduction for rule violations (applied by admins)
    private float $violationFee;

    private DbInterface $db;

    public function __construct(
        bool $isEnabled,
        string $creditCurrency,
        float $minBalanceCredit,
        float $rentalFee,
        int $priceCycle,
        float $longRentalFee,
        float $limitIncreaseFee,
        float $violationFee,
        DbInterface $db
    ) {
        if (!$isEnabled) {
            throw new \RuntimeException('Use DisabledCreditSystem instead');
        }
        if (
            $minBalanceCredit < 0
            || $rentalFee < 0
            || $longRentalFee < 0
            || $limitIncreaseFee < 0
            || $violationFee < 0
        ) {
            throw new \InvalidArgumentException('Credit values cannot be negative');
        }
        if (!in_array($priceCycle, [0, 1, 2], true)) {
            throw new \InvalidArgumentException('Invalid price cycle value');
        }
        $this->isEnabled = $isEnabled;
        $this->creditCurrency = $creditCurrency;
        $this->minBalanceCredit = $minBalanceCredit;
        $this->rentalFee = $rentalFee;
        $this->priceCycle = $priceCycle;
        $this->longRentalFee = $longRentalFee;
        $this->limitIncreaseFee = $limitIncreaseFee;
        $this->violationFee = $violationFee;
        $this->db = $db;
    }

    public function getUserCredit($userId): float
    {
        $result = $this->db->query('SELECT credit FROM credit WHERE userId = :userId', ['userId' => $userId]);
        if ($result->rowCount() == 0) {
            return 0;
        }

        return $result->fetchAssoc()['credit'];
    }

    public function getMinRequiredCredit(): float
    {
        return $this->minBalanceCredit + $this->rentalFee + $this->longRentalFee;
    }

    public function isEnoughCreditForRent($userid): bool
    {
        return $this->getUserCredit($userid) >= $this->getMinRequiredCredit();
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getCreditCurrency(): string
    {
        return $this->creditCurrency;
    }

    public function getRentalFee(): float
    {
        return $this->rentalFee;
    }

    public function getPriceCycle(): int
    {
        return $this->priceCycle;
    }

    public function getLongRentalFee(): float
    {
        return $this->longRentalFee;
    }

    public function getLimitIncreaseFee(): float
    {
        return $this->limitIncreaseFee;
    }

    public function getViolationFee(): float
    {
        return $this->violationFee;
    }
}
