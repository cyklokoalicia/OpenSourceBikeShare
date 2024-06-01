<?php

namespace BikeShare\Rent;

class RentSystemWeb extends AbstractRentSystem implements RentSystemInterface
{
    public static function getType(): string
    {
        return 'web';
    }

    protected function response($message, $error = 0)
    {
        $response = parent::response($message, $error);

        $json = json_encode($response);

        echo $json;
        exit;
    }
}
