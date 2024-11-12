<?php

declare(strict_types=1);

namespace BikeShare\App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private int $userId;
    private string $number;
    private string $password;
    private string $city;
    private string $userName;
    private int $privileges;

    public function __construct(
        int $userId,
        string $number,
        string $email,
        string $password,
        string $city,
        string $userName,
        int $privileges
    ) {
        $this->userId = $userId;
        $this->number = $number;
        $this->email = $email;
        $this->password = $password;
        $this->city = $city;
        $this->userName = $userName;
        $this->privileges = $privileges;
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
        return ['ROLE_USER'];
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
