<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Notifications\SmsNotification;

class WhereIsBike extends SmsNotification
{
    /**
     * @var Bike
     */
    private $bike;

    public function __construct(Bike $bike)
    {
        $this->bike = $bike;
    }

    public function text()
    {
        if ($this->bike->stand)
            return "Bike {$this->bike->bike_num} is at stand {$this->bike->stand->name}.";
        else
            return "Bike {$this->bike->bike_num} is rented by {$this->bike->user->name} ({$this->bike->user->phone_number}).";
    }
}
