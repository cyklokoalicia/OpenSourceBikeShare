<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\User\User;
use BikeShare\Notifications\SmsNotification;

class UnknownCommand extends SmsNotification
{
    public $command;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function text(User $user)
    {
        return "Error. The command {$this->command} does not exist. If you need help, send: HELP";
    }
}
