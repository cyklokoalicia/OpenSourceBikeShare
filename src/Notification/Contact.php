<?php

declare(strict_types=1);

namespace BikeShare\Notification;

class Contact
{
    /**
     * @param array<string, string> $addresses keyed by Channel->value
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $locale,
        public readonly array $addresses,
    ) {
    }

    public function addressFor(Channel $channel): ?string
    {
        return $this->addresses[$channel->value] ?? null;
    }
}
