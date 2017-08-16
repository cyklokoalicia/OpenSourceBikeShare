<?php

namespace BikeShare\Http\Services\Rents\Exceptions;

use MyCLabs\Enum\Enum;

class RentExceptionType extends Enum
{
    const BIKE_NOT_FREE = 1;
    const MAXIMUM_NUMBER_OF_RENTS = 2;
    const BIKE_NOT_ON_TOP = 3;
    const LOW_CREDIT = 4;
}