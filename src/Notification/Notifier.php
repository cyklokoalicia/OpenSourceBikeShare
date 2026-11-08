<?php

declare(strict_types=1);

namespace BikeShare\Notification;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Notifier implements NotifierInterface
{
    public function __construct(
        private readonly ContactResolver $contactResolver,
        private readonly ContainerInterface $channelSenders,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(Notification $notification, Recipients $recipients): void
    {
        foreach ($this->contactResolver->resolve($recipients) as $contact) {
            foreach ($notification->type->allowedChannels() as $channel) {
                $address = $contact->addressFor($channel);
                if ($address === null || !$this->channelSenders->has($channel->value)) {
                    continue;
                }

                try {
                    $this->channelSenders->get($channel->value)->send($address, $notification, $contact->locale);
                } catch (\Throwable $e) {
                    $this->logger->error('Notification delivery failed', [
                        'type' => $notification->type->value,
                        'channel' => $channel->value,
                        'userId' => $contact->userId,
                        'exception' => $e,
                    ]);
                }
            }
        }
    }
}
