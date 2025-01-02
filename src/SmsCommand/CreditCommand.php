<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CreditCommand implements SmsCommandInterface
{
    private const COMMAND_NAME = 'CREDIT';

    private CreditSystemInterface $creditSystem;
    private TranslatorInterface $translator;

    public function __construct(
        CreditSystemInterface $creditSystem,
        TranslatorInterface $translator
    ) {
        $this->creditSystem = $creditSystem;
        $this->translator = $translator;
    }

    public function execute(User $user, array $args): string
    {
        if (!$this->creditSystem->isEnabled()) {
            return $this->translator->trans(
                'Error. The command {badCommand} does not exist. If you need help, send: {helpCommand}',
                [
                    'badCommand' => self::COMMAND_NAME,
                    'helpCommand' => 'HELP'
                ]
            );
        }

        $userRemainingCredit = $this->creditSystem->getUserCredit($user->getUserId())
            . $this->creditSystem->getCreditCurrency();

        $message = $this->translator->trans('Your remaining credit: {credit}', ['credit' => $userRemainingCredit]);

        return $message;
    }

    public static function getName(): string
    {
        return self::COMMAND_NAME;
    }
}
