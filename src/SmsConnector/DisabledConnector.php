<?php

namespace BikeShare\SmsConnector;

class DisabledConnector extends AbstractConnector
{
    public function CheckConfig(array $config)
    {
    }

    public function Respond()
    {
    }

    public function Send($number, $text)
    {
    }
}