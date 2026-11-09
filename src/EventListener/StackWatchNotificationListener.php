<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\StackWatchViolationEvent;
use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Translation\TranslatableMessage;

class StackWatchNotificationListener
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(StackWatchViolationEvent $event): void
    {
        $user = $this->userRepository->findItem($event->getUserId());

        $this->notifier->notify(
            new Notification(
                NotificationType::StackWatch,
                new TranslatableMessage('notification.subject.stack_watch'),
                new TranslatableMessage(
                    'bike.rent.admin.stack_watch',
                    [
                        'bikeNumber' => $event->getBikeNumber(),
                        'userName' => $user['userName'],
                        'stackTopBike' => $event->getStackTopBike(),
                        'standName' => $event->getStandName(),
                    ]
                )
            ),
            new AdminRecipients()
        );
    }
}
