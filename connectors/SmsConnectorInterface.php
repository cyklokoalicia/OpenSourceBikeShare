<?php

interface SmsConnectorInterface
{
    public function CheckConfig(array $config);

    public function Text();

    public function ProcessedText();

    public function Number();

    public function UUID();

    public function Time();

    public function IPAddress();

    public function Respond();

    public function Send($number, $text);
}