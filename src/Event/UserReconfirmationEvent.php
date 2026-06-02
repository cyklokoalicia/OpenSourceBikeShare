<?php

declare(strict_types=1);

namespace BikeShare\Event;

use BikeShare\App\Entity\User;

class UserReconfirmationEvent
{
    public function __construct(
        private readonly User $user,
        private readonly string $userKey,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getUserKey(): string
    {
        return $this->userKey;
    }
}
