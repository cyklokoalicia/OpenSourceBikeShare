<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RentCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'RENT';

    public function __construct(
        TranslatorInterface $translator,
        private readonly RentSystemInterface $rentSystem
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user, int $bikeNumber): string
    {
        $response = $this->rentSystem->rentBike($user->getUserId(), $bikeNumber);

        return $response['message'];
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'RENT 42']);
    }
}
