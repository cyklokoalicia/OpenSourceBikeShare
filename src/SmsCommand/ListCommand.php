<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class ListCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'LIST';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        private readonly StandRepository $standRepository,
        private readonly bool $forceStack = false
    ) {
    }

    public function __invoke(User $user, string $standName): TranslatableInterface
    {
        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            throw new ValidationException('stand.error.unrecognized', ['standName' => $standName]);
        }

        $standInfo = $this->standRepository->findItemByName($standName);
        if (empty($standInfo)) {
            throw new ValidationException('stand.error.not_found', ['standName' => $standName]);
        }

        $bikesOnStand = $this->standRepository->findBikesOnStand((int)$standInfo['standId']);
        if (count($bikesOnStand) === 0) {
            return new TranslatableMessage(
                'command.list.empty',
                ['standName' => $standName]
            );
        }

        $stackTopBike = null;
        if ($this->forceStack) {
            $stackTopBike = $this->standRepository->findLastReturnedBikeOnStand((int)$standInfo['standId']);
        }

        $otherBikes = [];
        foreach ($bikesOnStand as $bike) {
            if ($this->forceStack && $bike['bikeNum'] == $stackTopBike) {
                continue;
            }
            $otherBikes[] = $bike['bikeNum'];
        }

        return new TranslatableMessage(
            'command.list.bikes',
            [
                'standName' => $standName,
                'hasFirstBike' => ($this->forceStack && !is_null($stackTopBike)) ? 'true' : 'false',
                'firstBike' => $stackTopBike ?? '',
                'otherBikes' => implode(', ', $otherBikes),
            ]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.list.help');
    }
}
