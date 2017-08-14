<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\SmsNotification;

class Credit extends SmsNotification
{
    private $creditCurrency;

    private $user;

    public function __construct(AppConfig $appConfig, User $user)
    {
        $this->creditCurrency = $appConfig->getCreditCurrency();
        $this->user = $user;
    }

    public function text()
    {
        $usercredit = $this->user->credit . $this->creditCurrency;
        return "Your remaining credit: {$usercredit}";
    }
}
