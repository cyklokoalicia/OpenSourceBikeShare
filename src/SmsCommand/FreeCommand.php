<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class FreeCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'FREE';

    public function __construct(
        private readonly BikeRepository $bikeRepository,
        private readonly StandRepository $standRepository
    ) {
    }

    public function __invoke(User $user): TranslatableInterface
    {
        $freeBikes = $this->bikeRepository->findFreeBikes();
        if (empty($freeBikes)) {
            return new TranslatableMessage(
                'command.free.message',
                [
                    'hasBikes' => 'false',
                    'bikesList' => '',
                    'hasEmptyStands' => 'false',
                    'standsList' => '',
                ]
            );
        }

        $bikesList = [];
        foreach ($freeBikes as $row) {
            $bikesList[] = $row['standName'] . ': ' . $row['bikeCount'];
        }

        $freeStands = $this->standRepository->findFreeStands();
        $standsList = [];
        foreach ($freeStands as $row) {
            $standsList[] = $row['standName'];
        }

        return new TranslatableMessage(
            'command.free.message',
            [
                'hasBikes' => 'true',
                'bikesList' => implode("\n", $bikesList),
                'hasEmptyStands' => empty($freeStands) ? 'false' : 'true',
                'standsList' => implode("\n", $standsList),
            ]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.free.help');
    }
}
