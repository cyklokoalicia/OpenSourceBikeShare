<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Notifications\SmsNotification;

class StandDoesNotExist extends SmsNotification
{
    private $standName;

    public function __construct($standName)
    {
        $this->standName = $standName;
    }

    public function smsText()
    {
        return "Stand name {$this->standName} does not exist.";
    }
}
