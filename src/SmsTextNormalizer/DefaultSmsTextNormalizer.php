<?php

declare(strict_types=1);

namespace BikeShare\SmsTextNormalizer;

class DefaultSmsTextNormalizer implements SmsTextNormalizerInterface
{
    public function normalize(string $text): string
    {
        return $text;
    }
}
