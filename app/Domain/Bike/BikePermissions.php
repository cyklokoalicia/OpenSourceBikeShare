<?php

namespace BikeShare\Domain\Bike;

use BikeShare\Helpers\MyEnum;

class BikePermissions extends MyEnum
{
    const RENT = "bike_rent";
    const RETURN = "bike_return";
    const WHERE = "bike_where";
    const ADD_NOTE = "bike_add_note";
    const DELETE_NOTE = "bike_delete_note";
    const REVERT = "bike_revert";
    const FORCE_RENT = "bike_force_rent";
    const FORCE_RETURN = "bike_force_return";
    const LAST_RENTS = "bike_last_rents";
}