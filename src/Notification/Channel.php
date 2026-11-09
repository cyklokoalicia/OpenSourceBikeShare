<?php

declare(strict_types=1);

namespace BikeShare\Notification;

enum Channel: string
{
    case Email = 'email';
    case Sms = 'sms';
}
