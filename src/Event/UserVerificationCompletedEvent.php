<?php

declare(strict_types=1);

namespace BikeShare\Event;

class UserVerificationCompletedEvent
{
    public function __construct(private readonly int $userId)
    {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }
}
