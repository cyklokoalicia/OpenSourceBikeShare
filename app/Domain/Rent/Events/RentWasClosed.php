<?php
namespace BikeShare\Domain\Rent\Events;

use BikeShare\Domain\Rent\Rent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class RentWasClosed
{
    use InteractsWithSockets, SerializesModels;

    public $rent;


    /**
     * Create a new event instance.
     *
     * @param Rent $rent
     *
     * @internal param Bike $bike
     */
    public function __construct(Rent $rent)
    {
        $this->rent = $rent;
    }
}
