<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

interface SmsConnectorInterface
{
    public function checkConfig(array $config): void;

    public function respond();

    public function receive(): void;

    public function send($number, $text): void;

    public function getMessage(): string;

    public function getProcessedMessage(): string;

    public function getNumber(): string;

    public function getUUID(): string;

    public function getTime(): string;

    public function getIPAddress(): string;

    public static function getType(): string;

    public function getMaxMessageLength(): int;
}
