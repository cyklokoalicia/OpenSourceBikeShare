<?php

namespace BikeShare\Notifications\Sms\Revert;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Notifications\SmsNotification;

class BikeNotRented extends SmsNotification
{
    private $bike;

    public function __construct(Bike $bike)
    {
        $this->bike = $bike;
    }

    public function smsText()
    {
        return "Bike {$this->bike->bike_num} is not rented right now, Revert not successful.";
    }
}
