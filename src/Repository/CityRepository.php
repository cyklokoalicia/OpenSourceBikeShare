<?php

declare(strict_types=1);

namespace BikeShare\Repository;

class CityRepository
{
    private array $cities;

    public function __construct(
        array $cities
    )
    {
        $this->cities = $cities;
    }

    public function findAvailableCities(): array
    {
        return $this->cities;
    }
}
