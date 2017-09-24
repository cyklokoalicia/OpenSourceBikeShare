<?php

namespace BikeShare\Notifications\Sms\Ret;

use BikeShare\Domain\Rent\Rent;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\SmsNotification;

class BikeReturnedSuccess extends SmsNotification
{
    /**
     * @var Rent
     */
    private $rent;

    private $noteText;

    /**
     * @var AppConfig
     */
    private $appConfig;

    public function __construct(AppConfig $appConfig, Rent $rent, $noteText = null)
    {
        $this->appConfig = $appConfig;
        $this->rent = $rent;
        $this->noteText = $noteText;
    }

    public function smsText()
    {
        $message = "Bike {$this->rent->bike->bike_num} returned to stand {$this->rent->standTo->name}. Make sure you set code to {$this->rent->new_code}.";
        if ($this->noteText){
            $message .= " (note: {$this->noteText})";
        }
        $message .= " Rotate lockpad to 0000.";

        if ($this->appConfig->isCreditEnabled()){
            $message .= " Credit: {$this->rent->user->credit} {$this->appConfig->getCreditCurrency()}";
            if ($this->rent->credit){
                $message .= " (-{$this->rent->credit})";
            }
        }
        return $message;
    }
}
