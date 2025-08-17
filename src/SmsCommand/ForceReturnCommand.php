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

    public function __construct(
        TranslatorInterface $translator,
        private readonly RentSystemInterface $rentSystem
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user, int $bikeNumber, string $standName, ?string $note = null): string
    {
        $response = $this->rentSystem->returnBike($user->getUserId(), $bikeNumber, $standName, '', true);
        return $response['message'];
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'FORCERETURN 42 MAINSQUARE note']);
    }
}
