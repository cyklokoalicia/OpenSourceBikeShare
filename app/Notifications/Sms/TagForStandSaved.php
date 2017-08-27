<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\SmsNotification;

class TagForStandSaved extends SmsNotification
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
        return "All bikes on stand {$this->stand->name} tagged.";
    }

}
