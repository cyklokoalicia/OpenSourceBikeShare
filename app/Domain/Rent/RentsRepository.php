<?php
namespace BikeShare\Domain\Rent;

use BikeShare\Domain\Core\Repository;

class RentsRepository extends Repository
{

    public function model()
    {
        return Rent::class;
    }
}
