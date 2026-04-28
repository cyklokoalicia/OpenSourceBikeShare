<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class CreditCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'CREDIT';

    public function __construct(
        private readonly CreditSystemInterface $creditSystem
    ) {
    }

    public function __invoke(User $user): TranslatableInterface
    {
        if (!$this->creditSystem->isEnabled()) {
            throw new ValidationException(
                'command.error.unknown_command',
                ['badCommand' => self::COMMAND_NAME, 'helpCommand' => 'HELP']
            );
        }

        return new TranslatableMessage(
            'command.credit.message',
            [
                'credit' => $this->creditSystem->getUserCredit($user->getUserId()),
                'creditCurrency' => $this->creditSystem->getCreditCurrency(),
            ]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.credit.help');
    }
}
