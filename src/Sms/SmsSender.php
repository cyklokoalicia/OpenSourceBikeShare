<?php

declare(strict_types=1);

namespace BikeShare\Sms;

use BikeShare\Repository\SentRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\SmsTextNormalizer\SmsTextNormalizerInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsSender implements SmsSenderInterface
{
    public function __construct(
        private readonly SmsConnectorInterface $smsConnector,
        private readonly SmsTextNormalizerInterface $smsTextNormalizer,
        private readonly SentRepository $sentRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function send(string $number, TranslatableInterface $message, ?string $locale = null): void
    {
        $text = $this->smsTextNormalizer->normalize($message->trans($this->translator, $locale));

        // `sent` is the history of what the user should have received, so it is logged even if it never arrives.
        $this->sentRepository->add($number, $text);
        $this->smsConnector->send($number, $text);
    }
}
