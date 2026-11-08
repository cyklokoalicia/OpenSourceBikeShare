<?php

declare(strict_types=1);

namespace BikeShare\Notification;

use BikeShare\Mail\MailSenderInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailChannelSender implements ChannelSenderInterface
{
    public function __construct(
        private readonly string $appName,
        private readonly MailSenderInterface $mailSender,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getChannelName(): string
    {
        return Channel::Email->value;
    }

    public function send(string $address, Notification $notification, ?string $locale): void
    {
        $subject = $notification->subject
            ?? new TranslatableMessage('admin.notification.subject', ['appName' => $this->appName]);

        $this->mailSender->sendMail(
            $address,
            $subject->trans($this->translator, $locale),
            $notification->body->trans($this->translator, $locale),
        );
    }
}
