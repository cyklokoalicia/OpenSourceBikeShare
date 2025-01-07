<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\App\Configuration;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class TooManyBikeRentEventListener
{
    private UserRepository $userRepository;
    private HistoryRepository $historyRepository;
    private Configuration $configuration;
    private TranslatorInterface $translator;
    private AdminNotifier $adminNotifier;

    public function __construct(
        UserRepository $userRepository,
        HistoryRepository $historyRepository,
        Configuration $configuration,
        TranslatorInterface $translator,
        AdminNotifier $adminNotifier
    ) {
        $this->userRepository = $userRepository;
        $this->historyRepository = $historyRepository;
        $this->configuration = $configuration;
        $this->translator = $translator;
        $this->adminNotifier = $adminNotifier;
    }

    public function __invoke(BikeRentEvent $event): void
    {
        $user = $this->userRepository->findItem($event->getUserId());
        $offsetTime = date(
            'Y-m-d H:i:s',
            time() - $this->configuration->get('watches')['timetoomany'] * 3600
        );

        $rentCount = $this->historyRepository->findRentCountByUser($event->getUserId(), $offsetTime);
        if ($rentCount >= ($user['userLimit'] + $this->configuration->get('watches')['numbertoomany'])) {
            $message = $this->translator->trans(
                'Bike rental over limit in {hour} hours',
                ['hour' => $this->configuration->get('watches')['timetoomany']]
            );
            $message .= PHP_EOL . $this->translator->trans(
                '{userName} ({phone}) rented {count} bikes',
                ['userName' => $user['userName'], 'phone' => $user['number'], 'count' => $rentCount]
            );

            $this->adminNotifier->notify($message, true, [$user['userId']]);
        }
    }
}
