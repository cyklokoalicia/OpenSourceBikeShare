<?php

declare(strict_types=1);

namespace BikeShare\Notification;

interface ChannelSenderInterface
{
    public static function getChannelName(): string;

    public function send(string $address, Notification $notification, string $locale): void;
}
