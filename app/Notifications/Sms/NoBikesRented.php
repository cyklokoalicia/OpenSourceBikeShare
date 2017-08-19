<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Notifications\SmsNotification;

class NoBikesRented extends SmsNotification
{
    public function text()
    {
        return "You have no rented bikes currently.";
    }
}
