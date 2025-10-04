<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HelpCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'HELP';

    public function __construct(
        TranslatorInterface $translator,
        private readonly CreditSystemInterface $creditSystem
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user): string
    {
        $availableCommands = [
            'HELP' => 0,
            'CREDIT' => 0,
            'FREE' => 0,
            'RENT bikeNumber' => 0,
            'RETURN bikeNumber standName' => 0,
            'WHERE bikeNumber' => 0,
            'INFO standName' => 0,
            'NOTE bikeNumber problem' => 0,
            'NOTE standName problem' => 0,
        ];
        if (!$this->creditSystem->isEnabled()) {
            unset($availableCommands['CREDIT']);
        }

        $message = 'Commands:' . PHP_EOL;
        if ($user->getPrivileges() > 0) {
            $availableCommands = array_merge(
                $availableCommands,
                [
                    'FORCERENT bikeNumber' => 0,
                    'FORCERETURN bikeNumber standName' => 0,
                    'LIST standName' => 0,
                    'LAST bikeNumber' => 0,
                    'REVERT bikeNumber' => 0,
                    'CODE bikeNumber code' => 0,
                    'ADD email phone fullname' => 0,
                    'DELNOTE bikeNumber [pattern]' => 0,
                    'DELNOTE standName [pattern]' => 0,
                    'TAG standName note for all bikes' => 0,
                    'UNTAG standName [pattern]' => 0,
                ]
            );
        }

        $message .= implode(PHP_EOL, array_keys($availableCommands));

        return $message;
    }

    public function getHelpMessage(): string
    {
        return '';
    }
}
