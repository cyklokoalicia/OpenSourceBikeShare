<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\SmsNotification;

class RentLimitExceeded extends SmsNotification
{
    /**
     * @var
     */
    private $userLimit;
    /**
     * @var
     */
    private $currentRents;

    public function __construct($userLimit, $currentRents)
    {
        $this->userLimit = $userLimit;
        $this->currentRents = $currentRents;
    }

    public function text()
    {
        if ($this->userLimit == 0) {
            return 'You can not rent any bikes. Contact the admins to lift the ban.';
        } else if ($this->userLimit == 1) {
            return "You can only rent 1 bike at once.";
        } else {
            return "You can only rent 1 bike at once and you have already rented {$this->currentRents}.";
        }
    }
}
