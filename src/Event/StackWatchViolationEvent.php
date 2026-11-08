<?php

declare(strict_types=1);

namespace BikeShare\Event;

class StackWatchViolationEvent
{
    public function __construct(
        private readonly int $bikeNumber,
        private readonly int $userId,
        private readonly ?int $stackTopBike,
        private readonly string $standName,
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

    public function getStackTopBike(): ?int
    {
        return $this->stackTopBike;
    }

    public function getStandName(): string
    {
        return $this->standName;
    }
}
