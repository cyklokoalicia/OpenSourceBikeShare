<?php

namespace BikeShare\Http\Services\Sms\Channels;

use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\Exceptions\CouldNotSendNotification;

class LogSmsChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed                                 $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     *
     */
    public function send($notifiable, Notification $notification)
    {
        $to = $this->getTo($notifiable);
        $message = $notification->toLogSms($notifiable);

        \Log::useDailyFiles(storage_path().'/logs/sms.log');
        \Log::notice(
            'SMS',
            ['Send sms to [' . $to . '] with text:' . PHP_EOL . $message]
        );
    }

    protected function getTo($notifiable)
    {
        if (isset($notifiable->phone_number)) {
            return $notifiable->phone_number;
        }

        throw CouldNotSendNotification::invalidReceiver();
    }
}