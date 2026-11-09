<?php

declare(strict_types=1);

namespace BikeShare\Notification;

use Symfony\Contracts\Translation\TranslatableInterface;

class Notification
{
    public function __construct(
        public readonly NotificationType $type,
        public readonly TranslatableInterface $subject,
        public readonly TranslatableInterface $body,
    ) {
    }
}
