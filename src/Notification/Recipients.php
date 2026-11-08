<?php

declare(strict_types=1);

namespace BikeShare\Notification;

class Recipients
{
    /**
     * @param list<int> $excludeUserIds
     */
    private function __construct(
        public readonly bool $admins,
        public readonly array $excludeUserIds,
        public readonly ?int $userId,
    ) {
    }

    /**
     * @param list<int|string> $excludeUserIds
     */
    public static function admins(array $excludeUserIds = []): self
    {
        return new self(true, array_values(array_map('intval', $excludeUserIds)), null);
    }

    public static function user(int $userId): self
    {
        return new self(false, [], $userId);
    }
}
