<?php

declare(strict_types=1);

namespace BikeShare\Notification;

use BikeShare\Sms\SmsSenderInterface;

class SmsChannelSender implements ChannelSenderInterface
{
    public function __construct(private readonly SmsSenderInterface $smsSender)
    {
    }

    public static function getChannelName(): string
    {
        return Channel::Sms->value;
    }

    public function send(string $address, Notification $notification, ?string $locale): void
    {
        $this->smsSender->send($address, $notification->body, $locale);
    }
}
