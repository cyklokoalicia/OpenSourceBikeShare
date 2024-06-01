<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use Symfony\Component\DependencyInjection\ServiceLocator;

class MailSenderFactory
{
    private ServiceLocator $locator;

    public function __construct(
        ServiceLocator $locator
    ) {
        $this->locator = $locator;
    }

    public function getMailSender(): MailSenderInterface
    {
        if (DEBUG === true) {
            return $this->locator->get(DebugMailSender::class);
        } else {
            return $this->locator->get(PHPMailerMailSender::class);
        }
    }
}
