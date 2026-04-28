<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\SmsCommand\Exception\ValidationException;

abstract class AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = '';
    protected const MIN_PRIVILEGES_LEVEL = 0;

    public static function getName(): string
    {
        return static::COMMAND_NAME;
    }

    public function checkPrivileges(User $user): void
    {
        if ($user->getPrivileges() < $this->getRequiredPrivileges()) {
            throw new ValidationException('command.error.privileges_required');
        }
    }

    protected function getRequiredPrivileges()
    {
        return static::MIN_PRIVILEGES_LEVEL;
    }
}
