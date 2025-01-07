<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\BikeRevertEvent;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\User\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

class BikeRevertEventListener
{
    private User $user;
    private SmsSenderInterface $smsSender;
    private TranslatorInterface $translator;

    public function __construct(
        User $user,
        SmsSenderInterface $smsSender,
        TranslatorInterface $translator
    ) {
        $this->user = $user;
        $this->smsSender = $smsSender;
        $this->translator = $translator;
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
