<?php

declare(strict_types=1);

namespace BikeShare\Event;

class BikeReturnEvent
{
    private int $bikeNumber;
    private string $standName;
    private int $userId;
    private bool $isForce;

    public function __construct(int $bikeNumber, string $standName, int $userId, bool $isForce)
    {
        $this->bikeNumber = $bikeNumber;
        $this->standName = $standName;
        $this->userId = $userId;
        $this->isForce = $isForce;
    }

    public function getBikeNumber(): int
    {
        return $this->bikeNumber;
    }

    public function getStandName(): string
    {
        return $this->standName;
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
