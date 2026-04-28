<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class RevertCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'REVERT';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        private readonly RentSystemInterface $rentSystem
    ) {
    }

    public function __invoke(User $user, int $bikeNumber): TranslatableInterface
    {
        return $this->rentSystem->revertBike($user->getUserId(), $bikeNumber);
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.revert.help');
    }
}
