<?php

declare(strict_types=1);

namespace BikeShare\Enum;

enum StandStatus: string
{
    case ACTIVE = 'active';
    case TECHNICAL = 'technical';
    case HIDDEN = 'hidden';
    case INACTIVE = 'inactive';

    public function isRentablePublic(): bool
    {
        return $this === self::ACTIVE;
    }
}
