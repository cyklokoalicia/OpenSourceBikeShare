<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Repository\RegistrationRepository;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserConfirmedEmailChecker implements UserCheckerInterface
{
    private RegistrationRepository $registrationRepository;
    private TranslatorInterface $translator;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        RegistrationRepository $registrationRepository,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->registrationRepository = $registrationRepository;
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $confirmation = $this->registrationRepository->findItemByUserId($user->getUserId());
        if (!empty($confirmation)) {
            $this->eventDispatcher->dispatch(new UserReconfirmationEvent($user));
            throw new CustomUserMessageAccountStatusException(
                $this->translator->trans('User does not confirmed email. Check your email for confirmation letter.')
            );
        }
    }
}
