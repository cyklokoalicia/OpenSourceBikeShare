<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\UserVerificationCompletedEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Translation\TranslatableMessage;

class NewUserAdminNotifierEventListener
{
    public function __construct(
        private readonly bool $isSmsSystemEnabled,
        private readonly AdminNotifier $adminNotifier,
        private readonly UserRepository $userRepository,
        private readonly RegistrationRepository $registrationRepository,
    ) {
    }

    public function __invoke(UserVerificationCompletedEvent $event): void
    {
        $userId = $event->getUserId();

        $user = $this->userRepository->findItem($userId);
        if ($user === null) {
            return;
        }

        if ($this->registrationRepository->findItemByUserId($userId) !== null) {
            return;
        }

        // When the SMS system is enabled, phone confirmation is required for full verification.
        // When it's disabled, users have no way to confirm phone, so email-confirm alone is enough.
        if ($this->isSmsSystemEnabled && (int)$user['isNumberConfirmed'] !== 1) {
            return;
        }

        $this->adminNotifier->notify(
            new TranslatableMessage(
                'admin.notification.new_verified_user',
                [
                    'userName' => $user['userName'],
                    'email' => $user['mail'],
                    'phone' => $user['number'],
                ]
            ),
            bySms: false,
        );
    }
}
