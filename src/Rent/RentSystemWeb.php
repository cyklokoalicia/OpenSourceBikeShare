<?php

namespace BikeShare\Rent;

class RentSystemWeb extends AbstractRentSystem implements RentSystemInterface
{
    protected function getRentSystemType()
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
