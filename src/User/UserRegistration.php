<?php

declare(strict_types=1);

namespace BikeShare\User;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\UserProvider;
use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Repository\CreditRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserRegistration
{
    private UserProvider $userProvider;
    private CreditRepository $creditRepository;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        UserProvider $userProvider,
        CreditRepository $creditRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->userProvider = $userProvider;
        $this->creditRepository = $creditRepository;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->eventDispatcher = $eventDispatcher;
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
        $this->creditRepository->addCredits($user->getUserId(), 0);

        $this->eventDispatcher->dispatch(new UserRegistrationEvent($user));

        return $user;
    }
}
