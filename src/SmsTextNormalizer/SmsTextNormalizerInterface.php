<?php

declare(strict_types=1);

namespace BikeShare\SmsTextNormalizer;

interface SmsTextNormalizerInterface
{
    public function normalize(string $text): string;
}