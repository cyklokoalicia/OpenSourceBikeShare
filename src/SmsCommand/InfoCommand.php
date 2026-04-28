<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class InfoCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'INFO';

    public function __construct(
        private readonly StandRepository $standRepository
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

        $latitude = round($standInfo['latitude'], 5);
        $longitude = round($standInfo['longitude'], 5);

        return new TranslatableMessage(
            'command.info.message',
            [
                'standName' => $standName,
                'description' => $standInfo['standDescription'],
                'hasGps' => ($latitude && $longitude) ? 'true' : 'false',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'hasPhoto' => $standInfo['standPhoto'] ? 'true' : 'false',
                'photo' => $standInfo['standPhoto'] ?? '',
            ]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.info.help');
    }
}
