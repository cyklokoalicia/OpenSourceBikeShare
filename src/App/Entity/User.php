<?php

declare(strict_types=1);

namespace BikeShare\App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly int $userId,
        private readonly string $number,
        private readonly string $email,
        private readonly string $password,
        private readonly string $city,
        private readonly string $userName,
        private readonly int $privileges,
        private readonly bool $isNumberConfirmed,
    ) {
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPrivileges(): int
    {
        return $this->privileges;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUsername(): string
    {
        return $this->userName;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getUserIdentifier(): string
    {
        return $this->number;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if ($this->privileges >= 1) {
            $roles[] = 'ROLE_ADMIN';
        }

        if ($this->privileges >= 7) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        return array_unique($roles);
    }

    public function isNumberConfirmed(): bool
    {
        return $this->isNumberConfirmed;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }
}
