<?php

declare(strict_types=1);

namespace BikeShare\Welcome;

class MessengerChat
{
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly MessengerIcon $icon,
    ) {
    }
}
