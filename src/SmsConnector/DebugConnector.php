<?php

namespace BikeShare\SmsConnector;

class DebugConnector extends AbstractConnector
{
    public function checkConfig(array $config)
    {
    }

    public function respond()
    {
    }

    public function send($number, $text)
    {
        echo $number . ' -&gt ' . $text . PHP_EOL;
    }
}
