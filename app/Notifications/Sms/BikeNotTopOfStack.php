<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Notifications\SmsNotification;

class BikeNotTopOfStack extends SmsNotification
{
    private $requestedBike;

    private $topBike;

    public function __construct(Bike $requestedBike, Bike $topBike)
    {
        $this->requestedBike = $requestedBike;
        $this->topBike = $topBike;
    }

    public function smsText()
    {
        return "Bike {$this->requestedBike->bike_num} is not rentable now, you have to rent bike {$this->topBike->bike_num} from this stand.";
    }
}
