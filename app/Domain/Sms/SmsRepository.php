<?php

namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Core\Repository;

class SmsRepository extends Repository
{
    public function model()
    {
        return Sms::class;
    }
}
