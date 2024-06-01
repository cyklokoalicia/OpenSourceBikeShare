<?php

namespace BikeShare\SmsConnector;

use BikeShare\App\Configuration;

abstract class AbstractConnector implements SmsConnectorInterface
{
    /**
     * @var bool
     */
    protected $debugMode = false;
    /**
     * @var string
     */
    protected $message = '';
    /**
     * @var string
     */
    protected $number = '';
    /**
     * @var string
     */
    protected $uuid = '';
    /**
     * @var string
     */
    protected $time = '';
    /**
     * @var string
     */
    protected $ipaddress = '';

    public function __construct(
        Configuration $configuration,
        $debugMode = false
    ) {
        $this->debugMode = $debugMode;
        $connectorConfig = json_decode($configuration->get('connectors')['config'][static::getType()] ?? '[]', true) ?? [];
        $this->checkConfig($connectorConfig);
    }


    public function getMessage()
    {
        return $this->message;
    }

    public function getProcessedMessage()
    {
        return strtoupper(trim(urldecode($this->message)));
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function getUUID()
    {
        return $this->uuid;
    }

    public function getTime()
    {
        return $this->time;
    }

    public function getIPAddress()
    {
        return $this->ipaddress;
    }

    abstract public function checkConfig(array $config);

    abstract public function send($number, $text);

    // confirm SMS received to API
    abstract public function respond();

    abstract public static function getType(): string;
}
