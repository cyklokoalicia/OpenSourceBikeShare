<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeRentEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class TooManyBikeRentEventListener
{
    public function __construct(
        private int $timeTooManyHours,
        private int $numberToMany,
        private UserRepository $userRepository,
        private HistoryRepository $historyRepository,
        private TranslatorInterface $translator,
        private AdminNotifier $adminNotifier,
    ) {
    }

    public function __invoke(BikeRentEvent $event): void
    {
        if ($event->isForce()) {
            // if force is true, then we don't need to check if user is over limit
            return;
        }

        $user = $this->userRepository->findItem($event->getUserId());
        $offsetTime = date(
            'Y-m-d H:i:s',
            time() - $this->timeTooManyHours * 3600
        );

        $rentCount = $this->historyRepository->findRentCountByUser($event->getUserId(), $offsetTime);
        if ($rentCount >= ($user['userLimit'] + $this->numberToMany)) {
            $message = $this->translator->trans(
                'Bike rental over limit in {hour} hours',
                ['hour' => $this->timeTooManyHours]
            );
            $message .= PHP_EOL . $this->translator->trans(
                '{userName} ({phone}) rented {count} bikes',
                ['userName' => $user['username'], 'phone' => $user['number'], 'count' => $rentCount]
            );

            $this->adminNotifier->notify($message, true, [$user['userId']]);
        }
    }
}
