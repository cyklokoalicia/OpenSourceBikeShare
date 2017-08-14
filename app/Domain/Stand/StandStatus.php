<?php

namespace BikeShare\Domain\Stand;

use ReflectionClass;

class StandStatus
{

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';
    const ACTIVE_SERVICE_RETURN_ONLY = 'active_service_return_only';
    const ACTIVE_RENT_ONLY = 'active_rent_only';
    const ACTIVE_RETURN_ONLY = 'active_return_only';


    public function toArray()
    {
        $constants = (new ReflectionClass(self::class))->getConstants();

        return $constants;
    }
}
