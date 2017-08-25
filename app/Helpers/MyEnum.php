<?php

namespace BikeShare\Helpers;

use MyCLabs\Enum\Enum;

class MyEnum extends Enum
{
    /**
     * Returns string/int values of enum constants as simple array
     * @return array
     */
    public static function textValues()
    {
        return array_map(function($obj){
            return $obj->getValue();
        }, array_values(static::values()));
    }
}