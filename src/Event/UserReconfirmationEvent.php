<?php

declare(strict_types=1);

namespace BikeShare\Event;

use BikeShare\App\Entity\User;

class UserReconfirmationEvent
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
