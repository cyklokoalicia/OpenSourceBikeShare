<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

class DebugMailSender implements MailSenderInterface, ResetInterface
{
    private array $sentMessages = [];

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function sendMail($recipient, $subject, $message)
    {
        $this->sentMessages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $message,
        ];
        $this->logger->debug('Sending email', compact('recipient', 'subject', 'message'));
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function reset()
    {
        $this->sentMessages = [];
    }
}
