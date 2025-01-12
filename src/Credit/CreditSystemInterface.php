<?php

declare(strict_types=1);

namespace BikeShare\Credit;

interface CreditSystemInterface
{
    public function getUserCredit($userId): float;

    public function getMinRequiredCredit(): float;

    public function isEnoughCreditForRent($userid): bool;

    public function isEnabled(): bool;

    public function getCreditCurrency(): string;

    public function getRentalFee(): float;

    public function getPriceCycle(): int;

    public function getLongRentalFee(): float;

    public function getLimitIncreaseFee(): float;

    public function getViolationFee(): float;
}
