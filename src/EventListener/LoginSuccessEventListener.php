<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\App\Entity\User;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Welcome\MessengerChatsProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessEventListener
{
    public function __construct(
        private readonly UserSettingsRepository $userSettingsRepository,
        private readonly MessengerChatsProvider $messengerChatsProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        if (!$event->getUser() instanceof User) {
            return;
        }

        $userId = $event->getUser()->getUserId();
        $settings = $this->userSettingsRepository->findByUserId($userId);
        $event->getRequest()->getSession()->set('_locale', $settings['locale']);

        if ($settings['showWelcomePage'] !== true) {
            return;
        }
        if (!$this->messengerChatsProvider->hasChats()) {
            return;
        }
        $request = $event->getRequest();
        if ($request->isXmlHttpRequest() || str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('welcome')
        ));
    }
}
