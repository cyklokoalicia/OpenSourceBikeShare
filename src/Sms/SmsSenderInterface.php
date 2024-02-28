<?php

namespace BikeShare\Sms;

interface SmsSenderInterface
{
    public function send($number, $message);
}
