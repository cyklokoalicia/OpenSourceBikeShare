<?php

namespace BikeShare\Rent;

interface RentSystemInterface
{
    public function rentBike($userId, $bikeId, $force = false);

    public function returnBike($userId, $bikeId, $standId, $note = '', $force = false);
}