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
        private readonly \DateTimeImmutable $registrationDate,
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
        // An unconfirmed user (when phone confirmation is required) is a newbie and
        // holds none of the regular roles; access is then limited to the
        // phone-confirmation flow via access_control.
        if (!$this->isNumberConfirmed) {
            return ['ROLE_NEWBIE'];
        }

        $roles = ['ROLE_USER'];
        if ($this->privileges >= 1) {
            $roles[] = 'ROLE_ADMIN';
        }

        if ($this->privileges >= 7) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        return array_unique($roles);
    }

    /**
     * Effective confirmation: true when the number is confirmed, or when phone
     * confirmation is not required at all (SMS system disabled). UserProvider folds
     * the SMS-enabled config in, so the entity itself stays config-agnostic.
     */
    public function isNumberConfirmed(): bool
    {
        return $this->isNumberConfirmed;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getRegistrationDate(): \DateTimeImmutable
    {
        return $this->registrationDate;
    }
}
