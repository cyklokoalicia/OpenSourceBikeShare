<?php
namespace BikeShare\Domain\User\Listeners;

use BikeShare\Domain\User\Events\UserWasRegistered;
use Illuminate\Events\Dispatcher;

class UserEventSubscriber
{
    /**
     * Handle user login events.
     */
    public function onUserLogin($event)
    {

    }

    /**
     * Handle user logout events.
     */
    public function onUserLogout($event)
    {

    }


    public function onUserRegister($event)
    {
        $event->user->assignRole('member');
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            'Illuminate\Auth\Events\Login',
            'BikeShare\Domain\User\Listeners\UserEventSubscriber@onUserLogin'
        );

        $events->listen(
            'Illuminate\Auth\Events\Logout',
            'BikeShare\Domain\User\Listeners\UserEventSubscriber@onUserLogout'
        );

        $events->listen(
            UserWasRegistered::class,
            UserEventSubscriber::class . '@onUserRegister'
        );
    }
}
