<?php

declare(strict_types=1);

namespace BikeShare\Notification;

interface NotifierInterface
{
    public function notify(Notification $notification, Recipients $recipients): void;
}
