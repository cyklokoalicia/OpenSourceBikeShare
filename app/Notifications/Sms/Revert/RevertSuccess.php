<?php

namespace BikeShare\Notifications\Sms\Revert;

use BikeShare\Domain\Rent\Rent;
use BikeShare\Notifications\SmsNotification;

class RevertSuccess extends SmsNotification
{
    private $rent;

    public function __construct(Rent $rent)
    {
        $this->rent = $rent;
    }

    public function smsText()
    {
        return "Bike {$this->rent->bike->bike_num} reverted to stand {$this->rent->standTo->name} with code {$this->rent->new_code}.";
    }
}
