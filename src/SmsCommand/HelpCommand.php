<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;

class HelpCommand implements SmsCommandInterface
{
    private const COMMAND_NAME = 'HELP';

    private CreditSystemInterface $creditSystem;

    public function __construct(
        CreditSystemInterface $creditSystem
    ) {
        $this->creditSystem = $creditSystem;
    }

    public function execute(User $user, array $args): string
    {
        $availableCommands = [
            'HELP' => 0,
            'CREDIT' => 0,
            'FREE' => 0,
            'RENT bikenumber' => 0,
            'RETURN bikenumber stand' => 0,
            'WHERE bikenumber' => 0,
            'INFO stand' => 0,
            'NOTE bikenumber problem_description' => 0,
            'NOTE stand problem_description' => 0,
        ];
        if (!$this->creditSystem->isEnabled()) {
            unset($availableCommands['CREDIT']);
        }
        $message = 'Commands:' . PHP_EOL;
        if ($user->getPrivileges() > 0) {
            $availableCommands = array_merge(
                $availableCommands,
                [
                    'FORCERENT bikenumber' => 0,
                    'FORCERETURN bikenumber stand' => 0,
                    'LIST stand' => 0,
                    'LAST bikenumber' => 0,
                    'REVERT bikenumber' => 0,
                    'ADD email phone fullname' => 0,
                    'DELNOTE bikenumber [pattern]' => 0,
                    'TAG stand note for all bikes' => 0,
                    'UNTAG stand [pattern]' => 0,
                ]
            );
        }
        $message .= implode(PHP_EOL, array_keys($availableCommands));

        return $message;
    }

    public static function getName(): string
    {
        return self::COMMAND_NAME;
    }
}
