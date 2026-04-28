<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class UnTagCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'UNTAG';

    public function __construct(
        private readonly StandRepository $standRepository,
        private readonly NoteRepository $noteRepository
    ) {
    }

    public function __invoke(User $user, string $standName, ?string $pattern = null): TranslatableInterface
    {
        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            throw new ValidationException('stand.error.unrecognized', ['standName' => $standName]);
        }

        $standInfo = $this->standRepository->findItemByName($standName);
        if (empty($standInfo)) {
            throw new ValidationException('stand.error.not_found', ['standName' => $standName]);
        }

        $count = $this->noteRepository->deleteNotesForAllBikesOnStand(
            (int)$standInfo['standId'],
            $pattern
        );

        if ($count === 0) {
            throw new ValidationException(
                'command.untag.error.no_notes',
                [
                    'standName' => $standName,
                    'hasPattern' => is_null($pattern) ? 'false' : 'true',
                    'pattern' => $pattern ?? '',
                ]
            );
        }

        return new TranslatableMessage(
            'command.untag.success',
            [
                'standName' => $standName,
                'count' => $count,
                'hasPattern' => is_null($pattern) ? 'false' : 'true',
                'pattern' => $pattern ?? '',
            ]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.untag.help');
    }
}
