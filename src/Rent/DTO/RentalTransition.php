<?php

declare(strict_types=1);

namespace BikeShare\Rent\DTO;

use BikeShare\Enum\Action;

class RentalTransition
{
    public function __construct(
        public readonly Action $action,
        public readonly int $userId,
        public readonly int $bikeNum,
        public readonly int $standId,
        public readonly string $parameter,
        public readonly ?int $pairActionId,
        public readonly \DateTimeImmutable $startedAt,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}
