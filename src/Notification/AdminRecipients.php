<?php

declare(strict_types=1);

namespace BikeShare\Notification;

/**
 * Every admin, optionally except the one who triggered the notification.
 */
class AdminRecipients implements RecipientsInterface
{
    public function __construct(
        public readonly ?int $exceptUserId = null,
    ) {
    }
}
