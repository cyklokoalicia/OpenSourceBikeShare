<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RevertCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'REVERT';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        TranslatorInterface $translator,
        private RentSystemInterface $rentSystem
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user, int $bikeNumber): string
    {
        return $this->rentSystem->revertBike($user->getUserId(), $bikeNumber);
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'REVERT 42']);
    }
}
