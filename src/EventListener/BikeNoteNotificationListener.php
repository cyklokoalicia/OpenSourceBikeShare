<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeReturnEvent;
use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Translation\TranslatableMessage;

class BikeNoteNotificationListener
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(BikeReturnEvent $event): void
    {
        if ($event->getNote() === null) {
            return;
        }

        $user = $this->userRepository->findItem($event->getUserId());
        $userName = $user['userName'];
        $phone = $user['number'];

        $this->notifier->notify(
            new Notification(
                NotificationType::BikeNoteAdded,
                new TranslatableMessage('notification.subject.bike_note_added'),
                new TranslatableMessage(
                    'bike.note.admin.notification',
                    [
                        'bikeNumber' => $event->getBikeNumber(),
                        // The event fires after the bike is back on the stand.
                        'bikeStatus' => new TranslatableMessage(
                            'bike.status.at_stand',
                            ['standName' => $event->getStandName()]
                        ),
                        'userName' => $userName,
                        'phone' => $phone,
                        'userNote' => $event->getNote(),
                    ]
                )
            ),
            new AdminRecipients()
        );
    }
}
