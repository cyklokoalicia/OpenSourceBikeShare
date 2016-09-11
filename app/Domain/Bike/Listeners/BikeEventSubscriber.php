<?php
namespace BikeShare\Domain\Bike\Listeners;

use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Bike\Events\BikeWasRented;
use BikeShare\Domain\Bike\Events\BikeWasReturned;
use Illuminate\Events\Dispatcher;

class BikeEventSubscriber
{
    /**
     * Handle user login events.
     */
    public function onBikeRent($event)
    {
        $event->bike->status = BikeStatus::OCCUPIED;
        $event->bike->current_code = $event->newCode;
        $event->bike->stand()->dissociate($event->bike->stand);
        $event->bike->user()->associate($event->user);
        $event->bike->save();
    }

    /**
     * Handle user logout events.
     */
    public function onBikeReturn($event)
    {
        $event->bike->status = BikeStatus::FREE;
        $event->bike->stand()->associate($event->stand);
        $event->bike->save();
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            BikeWasReturned::class,
            BikeEventSubscriber::class.'@onBikeReturn'
        );

        $events->listen(
            BikeWasRented::class,
            BikeEventSubscriber::class.'@onBikeRent'
        );
    }
}
