<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Notifications\SmsNotification;

class NoteTextMissing extends SmsNotification
{
    public function text()
    {
        return "Error: Note text is missing. Usage: NOTE [Bike number/Stand name] [Note text].";
    }
}
