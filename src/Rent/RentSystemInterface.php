<?php

namespace BikeShare\Rent;

interface RentSystemInterface
{
    public function rentBike($userId, $bikeId, $force = false);

    public function returnBike($userId, $bikeId, $standName, $note = '', $force = false);

    public function revertBike($userId, $bikeId);

    public static function getType(): string;
}
