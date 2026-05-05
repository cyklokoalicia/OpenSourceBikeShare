<?php

declare(strict_types=1);

namespace BikeShare\Enum;

enum StandStatus: string
{
    case ACTIVE = 'active';
    case TECHNICAL = 'technical';
    case HIDDEN = 'hidden';
    case INACTIVE = 'inactive';
    case VIRTUAL = 'virtual';

    public function isRentablePublic(): bool
    {
        return $this === self::ACTIVE || $this === self::VIRTUAL;
    }
}
