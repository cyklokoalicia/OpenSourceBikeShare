<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeRentEvent;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\Recipients;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Translation\TranslatableMessage;

class TooManyBikeRentEventListener
{
    public function __construct(
        private readonly int $timeTooManyHours,
        private readonly int $numberToMany,
        private readonly UserRepository $userRepository,
        private readonly HistoryRepository $historyRepository,
        private readonly NotifierInterface $notifier,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(BikeRentEvent $event): void
    {
        if ($event->isForce()) {
            // if force is true, then we don't need to check if user is over limit
            return;
        }

        $user = $this->userRepository->findItem($event->getUserId());
        $offsetTime = $this->clock->now()->sub(new \DateInterval('PT' . $this->timeTooManyHours . 'H'));

        $rentCount = $this->historyRepository->findRentCountByUser($event->getUserId(), $offsetTime);
        if ($rentCount >= ($user['userLimit'] + $this->numberToMany)) {
            $this->notifier->notify(
                new Notification(
                    NotificationType::TooManyRents,
                    new TranslatableMessage(
                        'admin.notification.too_many_rents',
                        [
                            'hour' => $this->timeTooManyHours,
                            'userName' => $user['userName'],
                            'phone' => $user['number'],
                            'count' => $rentCount,
                        ]
                    )
                ),
                Recipients::admins([$user['userId']])
            );
        }
    }
}
