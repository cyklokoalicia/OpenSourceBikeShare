<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use Symfony\Contracts\Translation\TranslatableInterface;

interface MailSenderInterface
{
    public function sendMail(
        string $recipient,
        TranslatableInterface $subject,
        TranslatableInterface $body,
        ?string $locale = null,
    ): void;
}
