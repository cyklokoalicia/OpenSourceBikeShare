<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\SmsNotification;

class BikeAlreadyRented extends SmsNotification
{
    /**
     * @var User
     */
    private $currentUser;
    /**
     * @var Bike
     */
    private $bike;

    public function __construct(User $currentUser, Bike $bike)
    {
        $this->currentUser = $currentUser;
        $this->bike = $bike;
    }

    public function smsText()
    {
        $bikeOwner = $this->bike->user;
        if ($this->currentUser->id == $bikeOwner->id) {
            $text = "You have already rented the bike {$this->bike->bike_num}".
            ". Code is {$this->bike->current_code}".
            ". Return bike with command: RETURN bikenumber standname.";
            return $text;
        } else {
            return "Bike {$this->bike->bike_num} is already rented.";
        }
    }
}
