<?php

declare(strict_types=1);

namespace BikeShare\Event;

use BikeShare\App\Entity\User;

class SmsProcessedEvent
{
    public const NAME = 'sms.processed';

    private User $user;
    private string $commandName;
    private array $commandArguments;
    private string $resultMessage;

    public function __construct(
        User $user,
        string $commandName,
        array $commandArguments,
        string $resultMessage
    ) {
        $this->user = $user;
        $this->commandName = $commandName;
        $this->commandArguments = $commandArguments;
        $this->resultMessage = $resultMessage;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getResultMessage(): string
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
