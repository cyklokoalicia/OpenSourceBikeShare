<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeRevertEvent;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Component\Translation\TranslatableMessage;

class BikeRevertEventListener
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SmsSenderInterface $smsSender,
        private readonly UserSettingsRepository $userSettingsRepository,
    ) {
    }

    public function __invoke(BikeRevertEvent $event): void
    {
        $user = $this->userRepository->findItem($event->getPreviousOwnerId());
        $phoneNumber = $user['number'] ?? null;
        if (
            !is_null($phoneNumber)
            && $event->getPreviousOwnerId() !== $event->getRevertedByUserId()
        ) {
            $locale = $this->userSettingsRepository->findByUserId($event->getPreviousOwnerId())['locale'] ?? null;
            $this->smsSender->send(
                $phoneNumber,
                new TranslatableMessage(
                    'bike.revert.notification.previous_owner',
                    ['bikeNumber' => $event->getBikeNumber()]
                ),
                $locale
            );
        }
    }
}
