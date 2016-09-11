<?php
namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Core\Repository;

class StandsRepository extends Repository
{

    public function model()
    {
        return Stand::class;
    }
}
