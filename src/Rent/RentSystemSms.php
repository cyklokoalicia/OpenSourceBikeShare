<?php

declare(strict_types=1);

namespace BikeShare\Rent;

use BikeShare\Rent\Enum\RentSystemType;

class RentSystemSms extends AbstractRentSystem implements RentSystemInterface
{
    public static function getType(): RentSystemType
    {
        return RentSystemType::SMS;
    }
}
