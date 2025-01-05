<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

class DisabledConnector extends AbstractConnector
{
    public function checkConfig(array $config): void
    {
    }

    public function respond()
    {
    }

    public function send($number, $text): void
    {
    }

    public function receive(): void
    {
    }

    public static function getType(): string
    {
        return 'disabled';
    }
}
