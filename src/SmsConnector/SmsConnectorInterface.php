<?php

namespace BikeShare\SmsConnector;

interface SmsConnectorInterface
{
    public function checkConfig(array $config);

    public function respond();

    public function send($number, $text);

    public function getMessage();

    public function getProcessedMessage();

    public function getNumber();

    public function getUUID();

    public function getTime();

    public function getIPAddress();
}