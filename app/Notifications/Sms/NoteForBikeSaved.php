<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Notifications\SmsNotification;

class NoteForBikeSaved extends SmsNotification
{

    /**
     * @var Bike
     */
    private $bike;

    public function __construct(Bike $bike)
    {
        $this->bike = $bike;
    }

    public function smsText()
    {
        return "Note for bike {$this->bike->bike_num} saved.";
    }
}
