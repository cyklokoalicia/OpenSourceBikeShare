<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Notifications\SmsNotification;

class NoBikesRented extends SmsNotification
{
    public function smsText()
    {
        return "You have no rented bikes currently.";
    }
}
