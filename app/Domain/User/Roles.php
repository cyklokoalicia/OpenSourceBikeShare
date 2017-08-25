<?php


namespace BikeShare\Domain\User;

use MyCLabs\Enum\Enum;

class Roles extends Enum
{
    const ADMIN = "admin";
    const USER = "user";
}