<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\SmsNotification;

class BikeDoesNotExist extends SmsNotification
{
    private $bikeNumber;

    public function __construct($bikeNumber)
    {
        $this->bikeNumber = $bikeNumber;
    }

    public function smsText()
    {
        return "Bike {$this->bikeNumber} does not exist.";
    }
}
