<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ForceReturnCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'FORCERETURN';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    private RentSystemInterface $rentSystem;

    public function __construct(
        TranslatorInterface $translator,
        RentSystemInterface $rentSystem
    ) {
        parent::__construct($translator);
        $this->rentSystem = $rentSystem;
    }

    public function __invoke(User $user, int $bikeNumber, string $standName, ?string $note = null): string
    {
        return $this->rentSystem->returnBike($user->getUserId(), $bikeNumber, $standName, $note, true);
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'FORCERETURN 42 MAINSQUARE note']);
    }
}
