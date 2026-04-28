<?php

declare(strict_types=1);

namespace BikeShare\Event;

use BikeShare\App\Entity\User;
use Symfony\Contracts\Translation\TranslatableInterface;

class SmsProcessedEvent
{
    public function __construct(
        private readonly User $user,
        private readonly string $commandName,
        private readonly array $commandArguments,
        private readonly TranslatableInterface $resultMessage,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getResultMessage(): TranslatableInterface
    {
        return $this->resultMessage;
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }

    public function getCommandArguments(): array
    {
        return $this->commandArguments;
    }
}
