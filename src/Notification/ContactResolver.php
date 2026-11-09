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
    public function resolve(RecipientsInterface $recipients): array
    {
        return match (true) {
            $recipients instanceof UserRecipient => $this->resolveUser($recipients),
            $recipients instanceof AdminRecipients => $this->resolveAdmins($recipients),
            default => throw new \LogicException('Unsupported recipients: ' . $recipients::class),
        };
    }

    /**
     * @return list<Contact>
     */
    private function resolveUser(UserRecipient $recipient): array
    {
        $user = $this->userRepository->findItem($recipient->userId);

        return $user === null ? [] : [$this->buildContact($user)];
    }

    /**
     * @return list<Contact>
     */
    private function resolveAdmins(AdminRecipients $recipients): array
    {
        $contacts = [];
        foreach ($this->userRepository->findAdmins() as $admin) {
            if ((int) $admin['userId'] === $recipients->exceptUserId) {
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
