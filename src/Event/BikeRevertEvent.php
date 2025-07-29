<?php

declare(strict_types=1);

namespace BikeShare\Event;

class BikeRevertEvent
{
    public function __construct(
        private int $bikeNumber,
        private int $revertedByUserId,
        private int $previousOwnerId,
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
