<?php

namespace BikeShare\SmsConnector;

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

    public function Text()
    {
        return $this->message;
    }

    public function ProcessedText()
    {
        return strtoupper(trim(urldecode($this->message)));
    }

    public function Number()
    {
        return $this->number;
    }

    public function UUID()
    {
        return $this->uuid;
    }

    public function Time()
    {
        return $this->time;
    }

    public function IPAddress()
    {
        return $this->ipaddress;
    }

    abstract public function CheckConfig(array $config);

    abstract public function Send($number, $text);

    // confirm SMS received to API
    abstract public function Respond();
}