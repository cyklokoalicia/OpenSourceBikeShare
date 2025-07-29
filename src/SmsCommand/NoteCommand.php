<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class NoteCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'NOTE';

    public function __construct(
        TranslatorInterface $translator,
        private BikeRepository $bikeRepository,
        private StandRepository $standRepository,
        private NoteRepository $noteRepository
    ) {
        parent::__construct($translator);
    }

    public function __invoke(
        User $user,
        ?int $bikeNumber = null,
        ?string $standName = null,
        ?string $note = null
    ): string {
        if (!is_null($bikeNumber)) {
            return $this->addBikeNote($user, $bikeNumber, $note);
        } elseif (!is_null($standName)) {
            return $this->addStandNote($user, $standName, $note);
        } else {
            throw new ValidationException($this->getHelpMessage());
        }
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans(
            'with bike number/stand name and problem description: {example}',
            ['example' => 'NOTE 42 ' . $this->translator->trans('Flat tire on front wheel')]
        );
    }

    private function addBikeNote(User $user, int $bikeNumber, string $note): string
    {
        if (empty($note)) {
            throw new ValidationException(
                $this->translator->trans(
                    'Empty note for bike {bikeNumber} not saved, for deleting notes use DELNOTE (for admins).',
                    ['bikeNumber' => $bikeNumber]
                )
            );
        }

        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            throw new ValidationException(
                $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber])
            );
        }

        $this->noteRepository->addNoteToBike(
            $bikeNumber,
            $user->getUserId(),
            $note
        );

        return $this->translator->trans(
            'Note "{note}" for bike {bikeNumber} saved.',
            ['note' => $note, 'bikeNumber' => $bikeNumber]
        );
    }

    private function addStandNote(User $user, string $standName, string $note)
    {
        if (empty($note)) {
            throw new ValidationException(
                $this->translator->trans(
                    'Empty note for stand {standName} not saved, for deleting notes use DELNOTE (for admins).',
                    ['standName' => $standName]
                )
            );
        }

        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            throw new ValidationException(
                $this->translator->trans(
                    'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                    ['standName' => $standName]
                )
            );
        }

        $standInfo = $this->standRepository->findItemByName($standName);

        if (empty($standInfo)) {
            throw new ValidationException(
                $this->translator->trans('Stand {standName} does not exist.', ['standName' => $standName])
            );
        }

        $this->noteRepository->addNoteToStand(
            (int)$standInfo['standId'],
            $user->getUserId(),
            $note
        );

        return $this->translator->trans(
            'Note "{note}" for stand {standName} saved.',
            ['note' => $note, 'standName' => $standName]
        );
    }
}
