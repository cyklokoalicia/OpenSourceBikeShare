<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeReturnEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Translation\TranslatableMessage;

class BikeNoteAdminNotificationListener
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BikeRepository $bikeRepository,
        private readonly AdminNotifier $adminNotifier,
    ) {
    }

    public function __invoke(BikeReturnEvent $event): void
    {
        $noteId = $event->getNoteId();
        if ($noteId === null) {
            return;
        }

        $userNote = $event->getNote() ?? '';

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

        $this->adminNotifier->notify(
            new TranslatableMessage(
                'bike.note.admin.notification',
                [
                    'noteId' => $noteId,
                    'bikeNumber' => $event->getBikeNumber(),
                    'bikeStatus' => $bikeStatus,
                    'userName' => $userName,
                    'phone' => $phone,
                    'userNote' => $userNote,
                ]
            )
        );
    }
}
