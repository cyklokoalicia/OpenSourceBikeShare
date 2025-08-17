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

    public function __construct(
        TranslatorInterface $translator,
        private readonly RentSystemInterface $rentSystem
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user, int $bikeNumber): string
    {
        $response = $this->rentSystem->rentBike($user->getUserId(), $bikeNumber, true);
        return $response['message'];
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'FORCERENT 42']);
    }
}
