<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class ForceReturnCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'FORCERETURN';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        private readonly RentSystemInterface $rentSystem
    ) {
    }

    public function __invoke(
        User $user,
        int $bikeNumber,
        string $standName,
        ?string $note = null,
    ): TranslatableInterface {
        return $this->rentSystem->returnBike($user->getUserId(), $bikeNumber, $standName, $note, true);
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.force_return.help');
    }
}
