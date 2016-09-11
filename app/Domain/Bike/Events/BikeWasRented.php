<?php
namespace BikeShare\Domain\Bike\Events;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class BikeWasRented
{
    use InteractsWithSockets, SerializesModels;

    public $bike;
    public $newCode;
    public $user;


    /**
     * Create a new event instance.
     *
     * @param Bike $bike
     * @param      $newCode
     *
     * @internal param User $user
     */
    public function __construct(Bike $bike, $newCode, $user)
    {
        $this->bike = $bike;
        $this->newCode = $newCode;
        $this->user = $user;
    }
}
