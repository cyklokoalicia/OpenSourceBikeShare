<?php

declare(strict_types=1);

namespace BikeShare\User;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\UserProvider;
use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserRegistration
{
    public function __construct(
        private readonly UserProvider $userProvider,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RegistrationRepository $registrationRepository,
        private readonly UserSettingsRepository $userSettingsRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function register(
        string $number,
        string $email,
        string $plainPassword,
        string $city,
        string $userName,
        int $privileges
    ): User {
        $registrationDate = new \DateTimeImmutable();
        $newUser = new User(
            0,
            $number,
            $email,
            '',
            $city,
            $userName,
            $privileges,
            false,
            $registrationDate,
        );
        $hashedPassword = $this->passwordHasher->hashPassword($newUser, $plainPassword);

        $user = $this->userProvider->addUser(
            $number,
            $email,
            $hashedPassword,
            $city,
            $userName,
            $privileges
        );
        $this->userRepository->updateUserLimit($user->getUserId(), 0);

        $userKey = bin2hex(random_bytes(32));
        $this->registrationRepository->addItem($user->getUserId(), $userKey);
        // The mail is translated in the recipient's saved locale, so persist the one they registered in.
        $this->userSettingsRepository->saveLocale($user->getUserId(), $this->translator->getLocale());

        $this->eventDispatcher->dispatch(new UserRegistrationEvent($user, $userKey));

        return $user;
    }
}
