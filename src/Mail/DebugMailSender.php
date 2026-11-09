<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

class DebugMailSender implements MailSenderInterface, ResetInterface
{
    private array $sentMessages = [];

    /** @var string[] */
    private array $failFor = [];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function sendMail($recipient, $subject, $message)
    {
        if (in_array($recipient, $this->failFor, true)) {
            throw new \RuntimeException('Debug delivery failure for ' . $recipient);
        }

        $this->sentMessages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $message,
        ];
        $this->logger->debug('Sending email', compact('recipient', 'subject', 'message'));
    }

    public function failFor(string $recipient): void
    {
        $this->failFor[] = $recipient;
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
        $this->failFor = [];
    }
}
