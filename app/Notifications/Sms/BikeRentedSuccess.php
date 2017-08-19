<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Rent\Rent;
use BikeShare\Notifications\SmsNotification;

class BikeRentedSuccess extends SmsNotification
{
    /**
     * @var Rent
     */
    private $rent;

    public function __construct(Rent $rent)
    {
        $this->rent = $rent;
    }

    public function text()
    {
        $msg = "Bike {$this->rent->bike->bike_num}: Open with code {$this->rent->old_code}. Change code immediately to {$this->rent->new_code} (open,rotate metal part,set new code,rotate metal part back).";
        $notes = $this->rent->bike->notes;

        if ($notes->count() > 0){
            $notesImploded = $notes->implode(';');
            $msg .= "(bike notes:{$notesImploded})";
        }
        return $msg;
    }
}
