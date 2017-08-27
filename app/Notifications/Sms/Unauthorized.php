<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\SmsNotification;
use Illuminate\Database\Eloquent\Collection;

class Unauthorized extends SmsNotification
{
    public function smsText()
    {
         return "Sorry, this command is only available for the privileged users.";
    }
}
