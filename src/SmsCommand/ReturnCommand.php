<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Rent\RentSystemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReturnCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'RETURN';

    public function __construct(
        TranslatorInterface $translator,
        private RentSystemInterface $rentSystem
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user, int $bikeNumber, string $standName, ?string $note = null): string
    {
        return $this->rentSystem->returnBike($user->getUserId(), $bikeNumber, $standName, $note);
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'RETURN 42 MAINSQUARE note']);
    }
}
