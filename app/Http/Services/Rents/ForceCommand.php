<?php

namespace BikeShare\Http\Services\Rents;

use BikeShare\Domain\User\User;

/**
 * Helper class for recording force commands
 */
class ForceCommand
{

    /**
     * @var User
     */
    public $user;

    public $forceCommand;


    public static function rent($user)
    {
        return new ForceCommand($user, "force_rent");
    }


    public static function retrn($user)
    {
        return new ForceCommand($user, "force_return");
    }


    public static function revert($user)
    {
        return new ForceCommand($user, "revert");
    }


    private function __construct(User $user, $forceCommand)
    {
        $this->user = $user;
        $this->forceCommand = $forceCommand;
    }
}