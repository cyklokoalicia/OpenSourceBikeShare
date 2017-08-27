<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\SmsNotification;

class RechargeCredit extends SmsNotification
{
    private $creditCurrency;

    private $userCredit;

    private $requiredCredit;

    public function __construct(AppConfig $appConfig, $userCredit, $requiredCredit)
    {
        $this->creditCurrency = $appConfig->getCreditCurrency();
        $this->userCredit = $userCredit;
        $this->requiredCredit = $requiredCredit;
    }

    public function smsText()
    {
        $text = 'Please, recharge your credit: '
             . $this->userCredit
             . $this->creditCurrency
             . ". Credit required: "
             . $this->requiredCredit
             . $this->creditCurrency . ".";
        return $text;


    }
}
