<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\SmsNotification;
use Mockery\Exception;

class NoNotesDeleted extends SmsNotification
{
    /**
     * @var Bike
     */
    private $bike;
    /**
     * @var
     */
    private $pattern;
    /**
     * @var User
     */
    private $user;
    /**
     * @var Stand
     */
    private $stand;

    public function __construct(User $user, $pattern, Bike $bike = null, Stand $stand = null)
    {
        if (!$bike && !$stand){
            throw new Exception("Neither bike nor stand specified");
        }

        $this->bike = $bike;
        $this->pattern = $pattern;
        $this->user = $user;
        $this->stand = $stand;
    }

    public function smsText()
    {
        $identifier = $this->bike ? "bike {$this->bike->bike_num}" : "stand {$this->stand->name}";

        if (empty($this->pattern)) {
            return "No notes found for {$identifier} to delete.";
        } else {
            return "No notes matching pattern '{$this->pattern}' found for {$identifier} to delete.";
        }
    }
}
