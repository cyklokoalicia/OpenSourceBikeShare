<?php

declare(strict_types=1);

namespace BikeShare\User;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\UserProvider;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserRegistration
{
    public function __construct(
        private UserProvider $userProvider,
        private CreditSystemInterface $creditSystem,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
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
        $user = $this->userProvider->addUser(
            $number,
            $email,
            $plainPassword,
            $city,
            $userName,
            $privileges
        );
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $plainPassword
        );

        $this->userProvider->upgradePassword($user, $hashedPassword);
        $this->userRepository->updateUserLimit($user->getUserId(), 0);
        $this->creditSystem->addCredit($user->getUserId(), 0);

        $this->eventDispatcher->dispatch(new UserRegistrationEvent($user));

        return $user;
    }
}
