<?php

declare(strict_types=1);

namespace BikeShare\Notification;

use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;

class ContactResolver
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserSettingsRepository $userSettingsRepository,
    ) {
    }

    /**
     * @return list<Contact>
     */
    public function resolve(Recipients $recipients): array
    {
        if ($recipients->userId !== null) {
            $user = $this->userRepository->findItem($recipients->userId);

            return $user === null ? [] : [$this->buildContact($user)];
        }

        $contacts = [];
        foreach ($this->userRepository->findAdmins() as $admin) {
            if (in_array((int) $admin['userId'], $recipients->excludeUserIds, true)) {
                continue;
            }
            $contacts[] = $this->buildContact($admin);
        }

        return $contacts;
    }

    private function buildContact(array $user): Contact
    {
        $userId = (int) $user['userId'];
        $addresses = [];
        if (!empty($user['mail'])) {
            $addresses[Channel::Email->value] = (string) $user['mail'];
        }
        if (!empty($user['number'])) {
            $addresses[Channel::Sms->value] = (string) $user['number'];
        }

        return new Contact(
            $userId,
            $this->userSettingsRepository->findByUserId($userId)['locale'] ?? null,
            $addresses,
        );
    }
}
