<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class NoteCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'NOTE';
    protected const ARGUMENT_COUNT = 3;

    private BikeRepository $bikeRepository;
    private StandRepository $standRepository;
    private NoteRepository $noteRepository;

    public function __construct(
        TranslatorInterface $translator,
        BikeRepository $bikeRepository,
        StandRepository $standRepository,
        NoteRepository $noteRepository
    ) {
        parent::__construct($translator);
        $this->bikeRepository = $bikeRepository;
        $this->standRepository = $standRepository;
        $this->noteRepository = $noteRepository;
    }

    protected function run(User $user, array $args): string
    {
        $note = urldecode(implode(' ', array_slice($args, self::ARGUMENT_COUNT - 1)));
        if (is_numeric(trim($args[1]))) {
            $bikeNumber = (int)(trim($args[1]));
            return $this->addBikeNote($user, $bikeNumber, $note);
        } else {
            $standName = strtoupper(trim($args[1]));
            return $this->addStandNote($user, $standName, $note);
        }
    }

    protected function getValidationErrorMessage(): string
    {
        return $this->translator->trans(
            'with bike number/stand name and problem description: {example}',
            ['example' => 'NOTE 42 ' . $this->translator->trans('Flat tire on front wheel')]
        );
    }

    private function addBikeNote(User $user, int $bikeNumber, string $note)
    {
        if (empty($note)) {
            return $this->translator->trans(
                'Empty note for bike {bikeNumber} not saved, for deleting notes use DELNOTE (for admins).',
                ['bikeNumber' => $bikeNumber]
            );
        }

        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            return $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber]);
        }

        $bikeCurrentUsage = $this->bikeRepository->findBikeCurrentUsage($bikeNumber);

        $phone = $bikeCurrentUsage["number"];
        $userName = $bikeCurrentUsage["userName"];
        $standName = $bikeCurrentUsage["standName"];

        if (!is_null($standName)) {
            $bikeStatus = $this->translator->trans(
                'Bike {bikeNumber} is at {standName}.',
                ['bikeNumber' => $bikeNumber, 'standName' => $standName]
            );
        } else {
            $bikeStatus = $this->translator->trans(
                'Bike {bikeNumber} is rented by {userName} (+{phone}). {note}',
                ['bikeNumber' => $bikeNumber, 'userName' => $userName, 'phone' => $phone, 'note' => '']
            );
        }

        $this->noteRepository->addNoteToBike(
            $bikeNumber,
            $user->getUserId(),
            $note
        );

        //TODO: notify admins
//        notifyAdmins(
//            _('Note #') . $noteid .
//            . $bikeStatus
//            . $note
//        );

        return $this->translator->trans('Note for bike {bikeNumber} saved.', ['bikeNumber' => $bikeNumber]);
    }

    private function addStandNote(User $user, string $standName, string $note)
    {
        if (empty($note)) {
            return $this->translator->trans(
                'Empty note for stand {standName} not saved, for deleting notes use DELNOTE (for admins).',
                ['standName' => $standName]
            );
        }

        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            return $this->translator->trans(
                'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                ['standName' => $standName]
            );
        }

        $standInfo = $this->standRepository->findItemByName($standName);

        if (empty($standInfo)) {
            return $this->translator->trans('Stand {standName} does not exist.', ['standName' => $standName]);
        }

        $this->noteRepository->addNoteToStand(
            $standInfo['standId'],
            $user->getUserId(),
            $note
        );

        //TODO: notify admins
//        notifyAdmins(
//            _('Note #') . $noteid . ": "
//            . _("on stand") . " " . $standName . " "
//            . _('by') . " " . $user->getUsername()
//            . " (" . $user->getNumber() . "):" . $userNote
//        );

        return $this->translator->trans('Note for stand {standName} saved.', ['standName' => $standName]);
    }
}
