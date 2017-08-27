<?php

namespace BikeShare\Notifications;

use NotificationChannels\Twilio\TwilioSmsMessage;

trait SmsChannelSendable
{
    public abstract function smsText();

    public function toTwilio($notifiable)
    {
        return (new TwilioSmsMessage())
            ->content($this->smsText());
    }

    public function toEuroSms($notifiable)
    {
        return $this->smsText();
    }

    public function toLogSms($notifiable)
    {
        return $this->smsText();
    }
}