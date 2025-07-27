<?php

namespace BikeShare\Rent;

class RentSystemWeb extends AbstractRentSystem implements RentSystemInterface
{
    protected function response($message, $error = 0)
    {
        //temp solution before full migration to new bootstrap
        $message = str_replace('badge badge-', 'label label-', $message);

        return parent::response($message, $error);
    }

    public static function getType(): string
    {
        return 'web';
    }
}
