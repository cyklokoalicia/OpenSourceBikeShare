<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Repository\UserSettingsRepository;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessEventListener
{
    public function __construct(
        private readonly UserSettingsRepository $userSettingsRepository,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event)
    {
        $userId = $event->getUser()->getUserId();
        $settings = $this->userSettingsRepository->findByUserId($userId);
        $event->getRequest()->getSession()->set('_locale', $settings['locale']);
    }
}
