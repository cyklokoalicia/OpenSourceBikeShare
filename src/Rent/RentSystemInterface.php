<?php

namespace BikeShare\Rent;

use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;

interface RentSystemInterface
{
    public function rentBike(int $userId, int $bikeId, bool $force = false): RentSystemResult;

    public function returnBike(
        int $userId,
        int $bikeId,
        string $standName,
        ?string $note = null,
        bool $force = false
    ): RentSystemResult;

    public function revertBike(int $userId, int $bikeId): RentSystemResult;

    public static function getType(): RentSystemType;
}
