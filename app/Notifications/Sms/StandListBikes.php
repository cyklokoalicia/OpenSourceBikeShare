<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\SmsNotification;

class StandListBikes extends SmsNotification
{
    /**
     * @var Stand
     */
    private $stand;

    public function __construct(Stand $stand)
    {
        $this->stand = $stand;
    }

    public function smsText()
    {
        $bikes = $this->stand->bikes;
        if ($bikes->count() == 0){
            return "Stand {$this->stand->name} is empty.";
        } else {
            return $bikes->count() . " bike(s) on stand {$this->stand->name}: " . $bikes->pluck('bike_num')->implode(',');
        }

    }
}
