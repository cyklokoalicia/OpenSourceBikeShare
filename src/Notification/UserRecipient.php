<?php

declare(strict_types=1);

namespace BikeShare\Notification;

class UserRecipient implements RecipientsInterface
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
