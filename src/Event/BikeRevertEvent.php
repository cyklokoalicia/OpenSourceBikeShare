<?php

declare(strict_types=1);

namespace BikeShare\Event;

class BikeRevertEvent
{
    public function __construct(
        private readonly int $bikeNumber,
        private readonly int $revertedByUserId,
        private readonly int $previousOwnerId,
    ) {
    }

    public function getBikeNumber(): int
    {
        return $this->bikeNumber;
    }

    public function getRevertedByUserId(): int
    {
        return $this->revertedByUserId;
    }

    public function getPreviousOwnerId(): int
    {
        return $this->previousOwnerId;
    }
}
