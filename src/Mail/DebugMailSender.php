<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use Psr\Log\LoggerInterface;

class DebugMailSender implements MailSenderInterface
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function sendMail($recipient, $subject, $message)
    {
        $this->logger->debug('Sending email', compact('recipient', 'subject', 'message'));
    }
}
