<?php

namespace BikeShare\SmsConnector;

class DisabledConnector extends AbstractConnector
{
    public function checkConfig(array $config)
    {
    }

    public function respond()
    {
    }

    public function send($number, $text)
    {
    }
}
