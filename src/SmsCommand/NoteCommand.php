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

class NoteCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'NOTE';

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
        ?string $note = null
    ): TranslatableInterface {
        if (!is_null($bikeNumber)) {
            return $this->addBikeNote($user, $bikeNumber, $note ?? '');
        }
        if (!is_null($standName)) {
            return $this->addStandNote($user, $standName, $note ?? '');
        }

        throw new ValidationException('command.note.help');
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.note.help');
    }

    private function addBikeNote(User $user, int $bikeNumber, string $note): TranslatableInterface
    {
        if ($note === '') {
            throw new ValidationException(
                'command.note.error.empty_bike_note',
                ['bikeNumber' => $bikeNumber]
            );
        }

        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            throw new ValidationException('bike.error.not_found', ['bikeNumber' => $bikeNumber]);
        }

        $this->noteRepository->addNoteToBike($bikeNumber, $user->getUserId(), $note);

        return new TranslatableMessage(
            'command.note.success_bike',
            ['note' => $note, 'bikeNumber' => $bikeNumber]
        );
    }

    private function addStandNote(User $user, string $standName, string $note): TranslatableInterface
    {
        if ($note === '') {
            throw new ValidationException(
                'command.note.error.empty_stand_note',
                ['standName' => $standName]
            );
        }

        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            throw new ValidationException('stand.error.unrecognized', ['standName' => $standName]);
        }

        $standInfo = $this->standRepository->findItemByName($standName);
        if (empty($standInfo)) {
            throw new ValidationException('stand.error.not_found', ['standName' => $standName]);
        }

        $this->noteRepository->addNoteToStand(
            (int)$standInfo['standId'],
            $user->getUserId(),
            $note
        );

        return new TranslatableMessage(
            'command.note.success_stand',
            ['note' => $note, 'standName' => $standName]
        );
    }
}
