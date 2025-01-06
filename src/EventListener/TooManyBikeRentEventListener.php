<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\App\Configuration;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Event\ManyRentEvent;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener(event: BikeRentEvent::NAME)]
class TooManyBikeRentEventListener
{
    private UserRepository $userRepository;
    private HistoryRepository $historyRepository;
    private Configuration $configuration;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        UserRepository $userRepository,
        HistoryRepository $historyRepository,
        Configuration $configuration,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->userRepository = $userRepository;
        $this->historyRepository = $historyRepository;
        $this->configuration = $configuration;
        $this->eventDispatcher = $eventDispatcher;
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
            $abusers = [];
            $abusers[] = [
                'userId' => $user['userId'],
                'userName' => $user['username'],
                'userPhone' => $user['number'],
                'rentCount' => $rentCount,
            ];
            $this->eventDispatcher->dispatch(
                new ManyRentEvent($abusers),
                ManyRentEvent::NAME
            );
        }
    }
}
