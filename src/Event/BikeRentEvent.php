<?php

declare(strict_types=1);

namespace BikeShare\Event;

class BikeRentEvent
{
    public const NAME = 'bike.rent';

    private int $bikeNumber;
    private int $userId;
    private bool $isForce;

    public function __construct(int $bikeNumber, int $userId, bool $isForce)
    {
        $this->bikeNumber = $bikeNumber;
        $this->userId = $userId;
        $this->isForce = $isForce;
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
