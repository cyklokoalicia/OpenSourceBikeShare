<?php

declare(strict_types=1);

namespace BikeShare\Event;

class BikeRentEvent
{
    public function __construct(
        private readonly int $bikeNumber,
        private readonly int $userId,
        private readonly bool $isForce,
    ) {
    }

    public function getBikeNumber(): int
    {
        return $this->bikeNumber;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function isForce(): bool
    {
        return $this->isForce;
    }
}
