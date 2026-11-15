<?php

declare(strict_types=1);

namespace BikeShare\Sms;

use BikeShare\Db\DbInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\SmsTextNormalizer\SmsTextNormalizerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsSender implements SmsSenderInterface
{
    public function __construct(
        private readonly SmsConnectorInterface $smsConnector,
        private readonly SmsTextNormalizerInterface $smsTextNormalizer,
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function send(string $number, TranslatableInterface $message, ?string $locale = null): void
    {
        $message = $this->smsTextNormalizer->normalize($message->trans($this->translator, $locale));
        $maxMessageLength = $this->smsConnector->getMaxMessageLength();
        $parts = strlen($message) > $maxMessageLength
            ? array_filter(
                array_map(trim(...), str_split($message, $maxMessageLength)),
                static fn (string $part): bool => $part !== ''
            )
            : [$message];

        // `sent` is the history of what the user should have received, so a part is logged even if it never arrives.
        foreach ($parts as $text) {
            $this->log($number, $text);
        }
        foreach ($parts as $text) {
            $this->smsConnector->send($number, $text);
        }
    }

    private function log(string $number, string $message): void
    {
        $this->db->query(
            'INSERT INTO sent SET number = :number, text = :message, time = :time',
            [
                'number' => $number,
                'message' => $message,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );
    }
}
