<?php

namespace BikeShare\Notifications;

use BikeShare\Http\Services\Sms\Channels\SmsChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

abstract class AdminNotification extends Notification
{
    use Queueable, SmsChannelSendable;

    public function via($notifiable)
    {
        // TODO select channel depending on user settings
        return [SmsChannelManager::class];
    }
}
