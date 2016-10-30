<?php
namespace BikeShare\Http\Services\Sms\Drivers;

class NullSmsDriver extends SmsService
{

    public function send($number, $text)
    {

    }
}
