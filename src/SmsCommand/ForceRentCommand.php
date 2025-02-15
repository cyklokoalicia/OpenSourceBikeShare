<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ForceRentCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'FORCERENT';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    private RentSystemInterface $rentSystem;

    public function __construct(
        TranslatorInterface $translator,
        RentSystemInterface $rentSystem
    ) {
        parent::__construct($translator);
        $this->rentSystem = $rentSystem;
    }

    public function __invoke(User $user, int $bikeNumber): string
    {
        return $this->rentSystem->rentBike($user->getUserId(), $bikeNumber, true);
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'FORCERENT 42']);
    }
}
