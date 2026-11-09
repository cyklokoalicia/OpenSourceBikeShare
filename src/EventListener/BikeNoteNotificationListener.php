<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeReturnEvent;
use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Translation\TranslatableMessage;

class BikeNoteNotificationListener
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BikeRepository $bikeRepository,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(BikeReturnEvent $event): void
    {
        if ($event->getNote() === null) {
            return;
        }

        $user = $this->userRepository->findItem($event->getUserId());
        $userName = $user['userName'] ?? '';
        $phone = $user['number'] ?? '';

        $bikeUsage = $this->bikeRepository->findBikeCurrentUsage($event->getBikeNumber());
        $standName = $bikeUsage['standName'] ?? null;
        if ($standName !== null) {
            $bikeStatus = new TranslatableMessage('bike.status.at_stand', ['standName' => $standName]);
        } else {
            $bikeStatus = new TranslatableMessage(
                'bike.status.in_use',
                ['userName' => $userName, 'phone' => $phone]
            );
        }

        $this->notifier->notify(
            new Notification(
                NotificationType::BikeNoteAdded,
                new TranslatableMessage('notification.subject.bike_note_added'),
                new TranslatableMessage(
                    'bike.note.admin.notification',
                    [
                        'bikeNumber' => $event->getBikeNumber(),
                        'bikeStatus' => $bikeStatus,
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
