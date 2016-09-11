<?php
namespace BikeShare\Domain\Bike\Events;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class BikeWasReturned
{
    use InteractsWithSockets, SerializesModels;

    public $bike;
    public $stand;


    /**
     * Create a new event instance.
     *
     * @param Bike $bike
     *
     * @internal param User $user
     */
    public function __construct(Bike $bike, Stand $stand)
    {
        $this->bike = $bike;
        $this->stand = $stand;
    }
}
