<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\App\Entity\ApiServiceUser;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ApiServiceUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // This provider does not support loading users by identifier.
        // ApiServiceUser is created by the ApiTokenAuthenticator.
        throw new \LogicException('Not implemented.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ApiServiceUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === ApiServiceUser::class;
    }
}
