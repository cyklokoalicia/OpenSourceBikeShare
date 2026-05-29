<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Welcome\MessengerChatsProvider;

class EnableWelcomePageOnRegistrationListener
{
    public function __construct(
        private readonly MessengerChatsProvider $messengerChatsProvider,
        private readonly UserSettingsRepository $userSettingsRepository,
    ) {
    }

    public function __invoke(UserRegistrationEvent $event): void
    {
        if (!$this->messengerChatsProvider->hasChats()) {
            return;
        }

        $this->userSettingsRepository->saveShowWelcomePage(
            $event->getUser()->getUserId(),
            true,
        );
    }
}
