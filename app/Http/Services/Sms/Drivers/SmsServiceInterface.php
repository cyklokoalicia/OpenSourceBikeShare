<?php
namespace BikeShare\Http\Services\Sms\Drivers;

interface SmsServiceInterface
{

    public function send($number, $text);
}
