<?php

namespace BikeShare\Http\Services\Sms\Channels;

use Illuminate\Notifications\Notification;

class NullSmsChannel
{
    public function send($notifiable, Notification $notification)
    {

    }
}