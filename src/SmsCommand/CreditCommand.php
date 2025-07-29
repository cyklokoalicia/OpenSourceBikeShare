<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CreditCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'CREDIT';

    public function __construct(
        TranslatorInterface $translator,
        private CreditSystemInterface $creditSystem
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user): string
    {
        if (!$this->creditSystem->isEnabled()) {
            throw new ValidationException(
                $this->translator->trans(
                    'Error. The command {badCommand} does not exist. If you need help, send: {helpCommand}',
                    [
                        'badCommand' => self::COMMAND_NAME,
                        'helpCommand' => 'HELP'
                    ]
                )
            );
        }

        $userRemainingCredit = $this->creditSystem->getUserCredit($user->getUserId())
            . $this->creditSystem->getCreditCurrency();

        $message = $this->translator->trans('Your remaining credit: {credit}', ['credit' => $userRemainingCredit]);

        return $message;
    }

    public function getHelpMessage(): string
    {
        return '';
    }
}
