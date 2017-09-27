<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\SmsNotification;

class NotRentableStand extends SmsNotification
{
    private $stand;


    public function __construct(Stand $stand)
    {
        $this->stand = $stand;
    }

    public function smsText()
    {
        return "Stand {$this->stand->name} is not rentable now";
    }
}
