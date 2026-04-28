<?php

declare(strict_types=1);

namespace BikeShare\Sms;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

class DebugSmsSender implements SmsSenderInterface, ResetInterface
{
    /** @var array<int, array{number: string, message: TranslatableInterface, locale: ?string}> */
    private array $sentMessages = [];

    public function __construct(
        private readonly SmsSender $inner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(string $number, TranslatableInterface $message, ?string $locale = null): void
    {
        $this->sentMessages[] = [
            'number' => $number,
            'message' => $message,
            'locale' => $locale,
        ];
        $this->logger->debug('Sending sms', ['number' => $number, 'locale' => $locale]);
        $this->inner->send($number, $message, $locale);
    }

    /**
     * @return array<int, array{number: string, message: TranslatableInterface, locale: ?string}>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
    }
}
