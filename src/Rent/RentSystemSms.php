<?php

declare(strict_types=1);

namespace BikeShare\Rent;

class RentSystemSms extends AbstractRentSystem implements RentSystemInterface
{
    public static function getType(): string
    {
        return 'sms';
    }

    protected function response($message, $error = 0): string
    {
        return strip_tags($message);
    }
}
