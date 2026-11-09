<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\UserRecipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatableMessage;

class RegistrationEventListener
{
    public function __construct(
        private readonly string $appName,
        private readonly string $systemRules,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly NotifierInterface $notifier,
    ) {
    }

    public function __invoke(UserRegistrationEvent $event): void
    {
        $user = $event->getUser();

        $names = preg_split("/[\s,]+/", $user->getUsername());
        $firstName = $names[0];
        $message = new TranslatableMessage(
            'success.registration.mail',
            [
                'name' => $firstName,
                'systemName' => $this->appName,
                'systemRulesPageUrl' => $this->systemRules,
                'emailConfirmURL' => $this->urlGenerator->generate(
                    'user_confirm_email',
                    ['key' => $event->getUserKey()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ]
        );

        $this->notifier->notify(
            new Notification(NotificationType::Registration, new TranslatableMessage('Registration'), $message),
            new UserRecipient($user->getUserId())
        );
    }
}
