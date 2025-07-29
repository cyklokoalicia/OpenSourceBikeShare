<?php

declare(strict_types=1);

namespace BikeShare\Event;

use BikeShare\App\Entity\User;

class UserReconfirmationEvent
{
    public function __construct(private User $user)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
