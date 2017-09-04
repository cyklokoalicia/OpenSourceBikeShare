<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\SmsNotification;

class NoBikesUntagged extends SmsNotification
{
    private $stand;
    private $pattern;

    public function __construct($pattern, Stand $stand)
    {
        $this->stand = $stand;
        $this->pattern = $pattern;
    }

    public function smsText()
    {
        if (empty($this->pattern)){
            return "No bikes with notes found for stand {$this->stand->name} to delete.";
        } else {
            return "No notes matching pattern {$this->pattern} found for bikes on stand {$this->stand->name} to delete.";
        }
    }
}
