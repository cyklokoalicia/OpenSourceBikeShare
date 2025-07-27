<?php

declare(strict_types=1);

namespace BikeShare\Credit;

interface CreditSystemInterface
{
    public function addCredit(int $userId, float $creditAmount, ?string $coupon): void;

    public function useCredit(int $userId, float $creditAmount): void;

    public function getUserCredit(int $userId): float;

    public function getMinRequiredCredit(): float;

    public function isEnoughCreditForRent(int $userid): bool;

    public function isEnabled(): bool;

    public function getCreditCurrency(): string;

    public function getRentalFee(): float;

    public function getPriceCycle(): int;

    public function getLongRentalFee(): float;

    public function getLimitIncreaseFee(): float;

    public function getViolationFee(): float;
}
