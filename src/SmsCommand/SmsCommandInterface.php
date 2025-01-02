<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;

interface SmsCommandInterface
{
    public function execute(User $user, array $args): string;

    public static function getName(): string;
}
