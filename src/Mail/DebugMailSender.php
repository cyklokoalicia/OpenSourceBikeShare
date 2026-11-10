<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

class DebugMailSender implements MailSenderInterface, ResetInterface
{
    /**
     * @var list<array{
     *     recipient: string,
     *     subject: TranslatableInterface,
     *     message: TranslatableInterface,
     *     locale: string
     * }>
     */
    private array $sentMessages = [];

    /** @var string[] */
    private array $failFor = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendMail(
        string $recipient,
        TranslatableInterface $subject,
        TranslatableInterface $body,
        string $locale,
    ): void {
        if (in_array($recipient, $this->failFor, true)) {
            throw new \RuntimeException('Debug delivery failure for ' . $recipient);
        }

        $this->sentMessages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $body,
            'locale' => $locale,
        ];
        $this->logger->debug('Sending email', compact('recipient', 'locale'));
    }

    public function failFor(string $recipient): void
    {
        $this->failFor[] = $recipient;
    }

    /**
     * @return list<array{
     *     recipient: string,
     *     subject: TranslatableInterface,
     *     message: TranslatableInterface,
     *     locale: string
     * }>
     */
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
