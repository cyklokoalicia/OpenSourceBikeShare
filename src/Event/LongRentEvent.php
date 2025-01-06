<?php

declare(strict_types=1);

namespace BikeShare\Event;

class LongRentEvent
{
    public const NAME = 'long_rent_event';

    private array $abusers;

    public function __construct(array $abusers)
    {
        $this->abusers = $abusers;
    }

    public function getAbusers(): array
    {
        return $this->abusers;
    }
}
