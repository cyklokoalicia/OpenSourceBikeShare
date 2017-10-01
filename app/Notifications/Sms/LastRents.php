<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Rent\Rent;
use BikeShare\Notifications\SmsNotification;

class LastRents extends SmsNotification
{

    /**
     * @var Bike
     */
    private $bike;


    /**
     * LastRents constructor.
     *
     * @param Bike $bike
     */
    public function __construct(Bike $bike)
    {
        $this->bike = $bike;
    }

    public function smsText()
    {
        $rents = Rent::where('bike_id', $this->bike->id)
            ->orderBy('started_at', 'desc')
            ->take(10)
            ->get();

        if (!$rents || $rents->count() === 0){
            return "B.{$this->bike->bike_num}: No previous rents.";
        }

        $texts = [];
        foreach ($rents as $rent){
            $texts[] = ($rent->standFrom ?: '-') . $rent->user->name . "({$rent->new_code})";
        }
        $summary = implode(',', $texts);
        return "B.{$this->bike->bike_num}:{$summary}";
    }
}
