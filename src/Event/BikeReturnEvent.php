<?php

declare(strict_types=1);

namespace BikeShare\Event;

class BikeReturnEvent
{
    public function __construct(
        private readonly int $bikeNumber,
        private readonly string $standName,
        private readonly int $userId,
        private readonly bool $isForce,
        private readonly ?string $note = null,
    ) {
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

    public function getNote(): ?string
    {
        return $this->note;
    }
}
