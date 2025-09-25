<?php

declare(strict_types=1);

namespace BikeShare\SmsTextNormalizer;

use ashtokalo\translit\Translit;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class AsciiSmsTextNormalizer implements SmsTextNormalizerInterface, LocaleAwareInterface
{
    public function __construct(
        private readonly Translit $translit,
        private string $locale,
    ) {
    }

    public function setLocale(string $locale)
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function normalize(string $text): string
    {
        return $this->translit->convert($text, $this->locale . ',cyrillic,ascii');
    }
}
