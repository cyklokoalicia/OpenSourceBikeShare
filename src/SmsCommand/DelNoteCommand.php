<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class DelNoteCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'DELNOTE';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        private readonly BikeRepository $bikeRepository,
        private readonly StandRepository $standRepository,
        private readonly NoteRepository $noteRepository
    ) {
    }

    public function __invoke(
        User $user,
        ?int $bikeNumber = null,
        ?string $standName = null,
        ?string $pattern = null
    ): TranslatableInterface {
        if (!is_null($bikeNumber)) {
            return $this->deleteBikeNote($bikeNumber, $pattern);
        }
        if (!is_null($standName)) {
            return $this->deleteStandNote($standName, $pattern);
        }

        throw new ValidationException('command.delnote.help');
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.delnote.help');
    }

    private function deleteBikeNote(int $bikeNumber, ?string $pattern): TranslatableInterface
    {
        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            throw new ValidationException('bike.error.not_found', ['bikeNumber' => $bikeNumber]);
        }

        $count = $this->noteRepository->deleteBikeNote($bikeNumber, $pattern);

        if ($count === 0) {
            throw new ValidationException(
                'command.delnote.error.no_bike_notes',
                [
                    'bikeNumber' => $bikeNumber,
                    'hasPattern' => is_null($pattern) ? 'false' : 'true',
                    'pattern' => $pattern ?? '',
                ]
            );
        }

        return new TranslatableMessage(
            'command.delnote.success_bike',
            [
                'bikeNumber' => $bikeNumber,
                'count' => $count,
                'hasPattern' => is_null($pattern) ? 'false' : 'true',
                'pattern' => $pattern ?? '',
            ]
        );
    }

    private function deleteStandNote(string $standName, ?string $pattern): TranslatableInterface
    {
        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            throw new ValidationException('stand.error.unrecognized', ['standName' => $standName]);
        }

        $standInfo = $this->standRepository->findItemByName($standName);
        if (empty($standInfo)) {
            throw new ValidationException('stand.error.not_found', ['standName' => $standName]);
        }

        $count = $this->noteRepository->deleteStandNote((int)$standInfo['standId'], $pattern);

        if ($count === 0) {
            throw new ValidationException(
                'command.delnote.error.no_stand_notes',
                [
                    'standName' => $standName,
                    'hasPattern' => is_null($pattern) ? 'false' : 'true',
                    'pattern' => $pattern ?? '',
                ]
            );
        }

        return new TranslatableMessage(
            'command.delnote.success_stand',
            [
                'standName' => $standName,
                'count' => $count,
                'hasPattern' => is_null($pattern) ? 'false' : 'true',
                'pattern' => $pattern ?? '',
            ]
        );
    }
}
