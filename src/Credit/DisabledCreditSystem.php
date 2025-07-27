<?php

declare(strict_types=1);

namespace BikeShare\Credit;

class DisabledCreditSystem implements CreditSystemInterface
{
    public function addCredit(int $userId, float $creditAmount, ?string $coupon): void
    {
    }

    public function useCredit(int $userId, float $creditAmount): void
    {
    }

    public function getUserCredit($userId): float
    {
        return 0;
    }

    public function getMinRequiredCredit(): float
    {
        return PHP_INT_MAX;
    }

    public function isEnoughCreditForRent($userid): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function getCreditCurrency(): string
    {
        return '';
    }

    public function getRentalFee(): float
    {
        return 0;
    }

    public function getPriceCycle(): int
    {
        return 0;
    }

    public function getLongRentalFee(): float
    {
        return 0;
    }

    public function getLimitIncreaseFee(): float
    {
        return 0;
    }

    public function getViolationFee(): float
    {
        return 0;
    }
}
