<?php

namespace BikeShare\Rent;

class RentSystemWeb extends AbstractRentSystem implements RentSystemInterface
{
    public static function getType(): string
    {
        return 'web';
    }
}
