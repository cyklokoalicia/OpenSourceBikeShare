<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\User\User;
use BikeShare\Notifications\SmsNotification;

class InvalidArgumentsCommand extends SmsNotification
{
    /**
     * @var
     */
    private $errorMessage;

    public function __construct($errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }

    public function smsText()
    {
        return 'Error. More arguments needed, use command ' . $this->errorMessage;
    }
}
