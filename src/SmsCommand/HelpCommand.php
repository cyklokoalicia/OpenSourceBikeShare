<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class HelpCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'HELP';

    public function __construct(
        private readonly CreditSystemInterface $creditSystem
    ) {
    }

    public function __invoke(User $user): TranslatableInterface
    {
        $availableCommands = [
            'HELP',
            'CREDIT',
            'FREE',
            'RENT bikeNumber',
            'RETURN bikeNumber standName',
            'WHERE bikeNumber',
            'INFO standName',
            'NOTE bikeNumber problem',
            'NOTE standName problem',
        ];
        if (!$this->creditSystem->isEnabled()) {
            $availableCommands = array_values(array_filter(
                $availableCommands,
                static fn(string $cmd): bool => $cmd !== 'CREDIT'
            ));
        }

        if ($user->getPrivileges() > 0) {
            $availableCommands = array_merge(
                $availableCommands,
                [
                    'FORCERENT bikeNumber',
                    'FORCERETURN bikeNumber standName',
                    'LIST standName',
                    'LAST bikeNumber',
                    'REVERT bikeNumber',
                    'CODE bikeNumber code',
                    'ADD email phone fullname',
                    'DELNOTE bikeNumber [pattern]',
                    'DELNOTE standName [pattern]',
                    'TAG standName note for all bikes',
                    'UNTAG standName [pattern]',
                ]
            );
        }

        return new TranslatableMessage(
            'command.help.message',
            ['commands' => implode("\n", $availableCommands)]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.help.help');
    }
}
