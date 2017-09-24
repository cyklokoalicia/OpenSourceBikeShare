<?php

namespace BikeShare\Notifications\Sms\Ret;

use BikeShare\Notifications\SmsNotification;

class BikeForceReturnedSuccess extends SmsNotification
{

    private $noteText;
    /**
     * @var
     */
    private $bikeNum;
    /**
     * @var
     */
    private $standName;
    /**
     * @var
     */
    private $newCode;

    public function __construct($bikeNum, $standName, $newCode, $noteText = null)
    {
        $this->noteText = $noteText;
        $this->bikeNum = $bikeNum;
        $this->standName = $standName;
        $this->newCode = $newCode;
    }

    public function smsText()
    {
        $message = "Bike {$this->bikeNum} returned to stand {$this->standName}. Make sure you set code to {$this->newCode}.";
        if ($this->noteText){
            $message .= " (note: {$this->noteText})";
        }
        $message .= " Rotate lockpad to 0000.";
        return $message;
    }
}
