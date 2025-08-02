<?php

declare(strict_types=1);

namespace BikeShare\App\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

class ApiServiceUser implements UserInterface
{
    public function __construct(
        private readonly string $identifier = 'api_service'
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}
