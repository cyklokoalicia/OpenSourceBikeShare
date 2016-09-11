<?php
namespace BikeShare\Domain\Rent\Listeners;

use BikeShare\Domain\Bike\Events\BikeWasRented;
use BikeShare\Domain\Bike\Events\BikeWasReturned;
use BikeShare\Domain\Rent\Events\RentWasCreated;
use BikeShare\Jobs\CreateNewRentJob;
use BikeShare\Jobs\FinishRentJob;
use Illuminate\Events\Dispatcher;

class RentEventSubscriber
{

    public function onRentCreated($event)
    {

    }


    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            RentWasCreated::class,
            RentEventSubscriber::class.'@onRentCreated'
        );
    }
}
