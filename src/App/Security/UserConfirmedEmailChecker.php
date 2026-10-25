<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\App\Entity\User;
use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Repository\RegistrationRepository;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserConfirmedEmailChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository,
        private readonly TranslatorInterface $translator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $confirmation = $this->registrationRepository->findItemByUserId($user->getUserId());
        if (!empty($confirmation)) {
            // Carry the userKey read here into the event so the listener never re-queries
            // (the row may be deleted concurrently by the email-confirmation flow).
            $this->eventDispatcher->dispatch(new UserReconfirmationEvent($user, (string)$confirmation['userKey']));

            throw new EmailUnconfirmedException(
                $this->translator->trans('User does not confirmed email. Check your email for confirmation letter.')
            );
        }
    }
}
