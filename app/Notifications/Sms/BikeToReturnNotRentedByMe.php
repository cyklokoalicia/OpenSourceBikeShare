<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\SmsNotification;
use Illuminate\Database\Eloquent\Collection;

class BikeToReturnNotRentedByMe extends SmsNotification
{
    /**
     * @var User
     */
    private $user;
    /**
     * @var Bike
     */
    private $bikeToReturn;

    /**
     * @var Collection
     */
    private $userRentedBikes;

    public function __construct(User $user, Bike $bikeToReturn, $userRentedBikes = null)
    {

        $this->user = $user;
        $this->bikeToReturn = $bikeToReturn;
        $this->userRentedBikes = $userRentedBikes;
    }

    public function smsText()
    {
        $msg = "You do not have bike  {$this->bikeToReturn->bike_num} rented.";
        if ($this->userRentedBikes && $this->userRentedBikes->count() > 0){
            $msg .= " You have rented the following bikes: " . $this->userRentedBikes->pluck('bike_num')->implode(', ');
        }
        return $msg;
    }
}
