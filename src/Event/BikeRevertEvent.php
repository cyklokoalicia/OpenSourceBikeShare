<?php

declare(strict_types=1);

namespace BikeShare\Event;

class BikeRevertEvent
{
    public const NAME = 'bike.revert';

    private int $bikeNumber;
    private int $revertedByUserId;
    private int $previousOwnerId;

    public function __construct(int $bikeNumber, int $revertedByUserId, int $previousUserId)
    {
        $this->bikeNumber = $bikeNumber;
        $this->revertedByUserId = $revertedByUserId;
        $this->previousOwnerId = $previousUserId;
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
