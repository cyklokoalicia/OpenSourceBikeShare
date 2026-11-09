<?php

declare(strict_types=1);

namespace BikeShare\Notification;

use BikeShare\Mail\MailSenderInterface;

class EmailChannelSender implements ChannelSenderInterface
{
    public function __construct(private readonly MailSenderInterface $mailSender)
    {
    }

    public static function getChannelName(): string
    {
        return Channel::Email->value;
    }

    public function send(string $address, Notification $notification, string $locale): void
    {
        $this->mailSender->sendMail(
            $address,
            $notification->subject,
            $notification->body,
            $locale,
        );
    }
}
