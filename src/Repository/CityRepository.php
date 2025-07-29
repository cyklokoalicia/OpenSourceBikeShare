<?php

declare(strict_types=1);

namespace BikeShare\Repository;

class CityRepository
{
    public function __construct(private array $cities)
    {
    }

    public function findAvailableCities(): array
    {
        return $this->cities;
    }
}
