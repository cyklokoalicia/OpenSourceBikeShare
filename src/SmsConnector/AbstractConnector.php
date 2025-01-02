<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

abstract class AbstractConnector implements SmsConnectorInterface
{
    protected bool $debugMode = false;
    protected string $message = '';
    protected string $number = '';
    protected string $uuid = '';
    protected string $time = '';
    protected string $ipaddress = '';

    public function __construct(
        array $configuration,
        $debugMode = false
    ) {
        $this->debugMode = $debugMode;
        $connectorConfig = $configuration[static::getType()] ?? [];
        $this->checkConfig($connectorConfig);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getProcessedMessage(): string
    {
        return strtoupper(trim(urldecode($this->message)));
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getUUID(): string
    {
        return $this->uuid;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function getIPAddress(): string
    {
        return $this->ipaddress;
    }

    abstract public function checkConfig(array $config): void;

    abstract public function send($number, $text): void;

    abstract public function receive(): void;

    // confirm SMS received to API
    abstract public function respond();

    abstract public static function getType(): string;
}
