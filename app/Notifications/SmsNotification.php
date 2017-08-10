<?php

namespace BikeShare\Notifications;

use BikeShare\Domain\User\User;
use BikeShare\Http\Services\Sms\Channels\SmsChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioSmsMessage;

abstract class SmsNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return [SmsChannelManager::class];
    }

    public abstract function text(User $notifiable);

    public function toTwilio($notifiable)
    {
        return (new TwilioSmsMessage())
            ->content($this->text($notifiable));
    }

    public function toEuroSms($notifiable)
    {
        return $this->text($notifiable);
    }

    public function toLogSms($notifiable)
    {
        return $this->text($notifiable);
    }
}
