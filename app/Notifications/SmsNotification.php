<?php

namespace BikeShare\Notifications;

use BikeShare\Http\Services\Sms\Channels\SmsChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

abstract class SmsNotification extends Notification
{
    use Queueable, SmsChannelSendable;

    public function via($notifiable)
    {
        return [SmsChannelManager::class];
    }
}
