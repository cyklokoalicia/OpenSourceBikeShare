<?php

namespace BikeShare\Notifications\Sms\Rent;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Notifications\SmsNotification;

class ForceRentOverrideRent extends SmsNotification
{
    private $bike;

    public function __construct(Bike $bike)
    {
        $this->bike = $bike;
    }

    public function smsText()
    {
        return "System override: Your rented bike {$this->bike->bike_num} has been rented by admin.";
    }
}
