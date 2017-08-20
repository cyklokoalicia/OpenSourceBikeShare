<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\SmsNotification;

class StandInfo extends SmsNotification
{
    /**
     * @var Stand
     */
    private $stand;

    public function __construct(Stand $stand)
    {
        $this->stand = $stand;
    }

    public function text()
    {
        $msg = $this->stand->name . " - " . $this->stand->description;
        if ($this->stand->longitude && $this->stand->latitude){
            $msg .= ", GPS: " . $this->stand->latitude . "," . $this->stand->longitude;
        }

        // TODO add stand photo link - figure out whether to store blob or photo link
//        if ($standPhoto) $message .= ", " . $standPhoto;

        return $msg;
    }
}
