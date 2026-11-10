<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeRevertEvent;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\UserRecipient;
use Symfony\Component\Translation\TranslatableMessage;

class BikeRevertEventListener
{
    public function __construct(
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(BikeRevertEvent $event): void
    {
        if ($event->getPreviousOwnerId() === $event->getRevertedByUserId()) {
            return;
        }

        $this->notifier->notify(
            new Notification(
                NotificationType::BikeRevertedOwner,
                new TranslatableMessage('notification.subject.bike_reverted_owner'),
                new TranslatableMessage(
                    'bike.revert.notification.previous_owner',
                    ['bikeNumber' => $event->getBikeNumber()]
                )
            ),
            new UserRecipient($event->getPreviousOwnerId())
        );
    }
}
