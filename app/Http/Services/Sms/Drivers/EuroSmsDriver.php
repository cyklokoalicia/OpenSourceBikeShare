<?php
namespace BikeShare\Http\Services\Sms\Drivers;

use BikeShare\Http\Services\Sms\EuroSms;

class EuroSmsDriver extends SmsService
{

    public $euroSms;


    public function __construct(EuroSms $euroSms)
    {
        $this->euroSms = $euroSms;
    }


    public function send($number, $text)
    {
        $this->euroSms->makeRequest($number, $text);
    }
}
