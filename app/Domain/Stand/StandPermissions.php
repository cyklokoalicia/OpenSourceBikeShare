<?php

namespace BikeShare\Domain\Stand;

use BikeShare\Helpers\MyEnum;

class StandPermissions extends MyEnum
{
    const ADD_NOTE = "stand_add_note";
    const DEL_NOTE = "stand_del_note";
    const TAG = "stand_tag";
    const UNTAG = "stand_untag";
}