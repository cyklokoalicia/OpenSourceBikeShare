<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeRevertEvent;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class BikeRevertEventListener
{
    public function __construct(
        private readonly User $user,
        private readonly SmsSenderInterface $smsSender,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(BikeRevertEvent $event): void
    {
        $phoneNumber = $this->user->findPhoneNumber($event->getPreviousOwnerId());
        if (
            !is_null($phoneNumber)
            && $event->getPreviousOwnerId() !== $event->getRevertedByUserId()
        ) {
            $this->smsSender->send(
                $phoneNumber,
                $this->translator->trans(
                    'Bike {bikeNumber} has been returned. You can now rent a new bicycle.',
                    ['bikeNumber' => $event->getBikeNumber()]
                )
            );
        }
    }
}
