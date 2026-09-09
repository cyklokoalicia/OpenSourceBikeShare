<?php

declare(strict_types=1);

namespace BikeShare\Rent\DTO;

class RentalWriteResult
{
    public function __construct(
        public readonly int $rentId,
        public readonly int $eventId,
        public readonly RentalTransition $transition,
    ) {
    }
}
