<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Notifications\SmsNotification;

class NoteForStandSaved extends SmsNotification
{
    /**
     * @var Stand
     */
    private $stand;

    public function __construct(Stand $stand)
    {
        $this->stand = $stand;
    }

    public function smsText()
    {
        return "Note for stand {$this->stand->name} saved.";
    }
}
