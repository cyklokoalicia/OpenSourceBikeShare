<?php

namespace BikeShare\Providers;

use BikeShare\Domain\Bike\Listeners\BikeEventSubscriber;
use BikeShare\Domain\Rent\Listeners\RentEventSubscriber;
use BikeShare\Domain\User\Listeners\UserEventSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'BikeShare\Events\SomeEvent' => [
            'BikeShare\Listeners\EventListener',
        ],
    ];

    protected $subscribe = [
        UserEventSubscriber::class,
        RentEventSubscriber::class,
        BikeEventSubscriber::class
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
