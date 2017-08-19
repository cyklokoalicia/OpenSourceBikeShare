<?php

namespace BikeShare\Domain\Stand;

use BikeShare\Domain\Core\Repository;
use BikeShare\Domain\Sms\Sms;

class SmsRepository extends Repository
{
    public function model()
    {
        return Sms::class;
    }
}
