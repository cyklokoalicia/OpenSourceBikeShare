<?php

namespace BikeShare\Domain\Stand;

class StandService
{

    public static function chooseIcon(Stand $stand)
    {
        $bikes = $stand->bikes->count();
        if ($bikes) {
            return asset('img/icon.png');
        }

        return asset('img/icon-none.png');
    }
}
