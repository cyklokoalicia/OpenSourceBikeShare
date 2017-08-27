<?php

namespace BikeShare\Notifications\Admin;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\AdminNotification;
use Mockery\Exception;

class NotesDeleted extends AdminNotification
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
     * @var
     */
    private $deletedCount;
    /**
     * @var User
     */
    private $user;
    /**
     * @var Stand
     */
    private $stand;

    public function __construct(User $user, $pattern, $deletedCount, Bike $bike = null, Stand $stand = null)
    {
        if (!$bike && !$stand){
            throw new Exception("Neither bike nor stand defined");
        }
        $this->bike = $bike;
        $this->pattern = $pattern;
        $this->deletedCount = $deletedCount;
        $this->user = $user;
        $this->stand = $stand;
    }

    public function smsText()
    {
        $id = $this->bike ? "bike {$this->bike->bike_num}" : "stand {$this->stand->name}";

        if (empty($this->pattern)) {
            return "All {$this->deletedCount} note(s) for $id deleted by {$this->user->name}.";
        } else {
            return "{$this->deletedCount} note(s) for $id matching '{$this->pattern}' deleted by {$this->user->name}.";
        }
    }
}
