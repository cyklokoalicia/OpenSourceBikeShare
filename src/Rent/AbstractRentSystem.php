<?php

namespace BikeShare\Rent;

abstract class AbstractRentSystem
{
    abstract protected function getRentSystemType();
    abstract protected function response($message, $error = 0, $additional = '', $log = 1);
}