<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use Symfony\Component\DependencyInjection\ServiceLocator;

class MailSenderFactory
{
    private string $smtpHost;
    private ServiceLocator $locator;

    public function __construct(
        string $smtpHost,
        ServiceLocator $locator
    ) {
        $this->smtpHost = $smtpHost;
        $this->locator = $locator;
    }

    public function getMailSender(): MailSenderInterface
    {
        if (empty($this->smtpHost)) {
            return $this->locator->get(DebugMailSender::class);
        } else {
            return $this->locator->get(PHPMailerMailSender::class);
        }
    }
}
