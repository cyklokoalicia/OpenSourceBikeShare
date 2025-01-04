<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class DelNoteCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'DELNOTE';
    protected const ARGUMENT_COUNT = 2;
    protected const MIN_PRIVILEGES_LEVEL = 1;

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
        $note = urldecode(implode(' ', array_slice($args, self::ARGUMENT_COUNT)));
        if (is_numeric(trim($args[1]))) {
            $bikeNumber = (int)(trim($args[1]));
            return $this->deleteBikeNote($user, $bikeNumber, $note);
        } else {
            $standName = strtoupper(trim($args[1]));
            return $this->deleteStandNote($user, $standName, $note);
        }
    }

    protected function getValidationErrorMessage(): string
    {
        return $this->translator->trans(
            'with bike number and optional pattern. All messages or notes matching pattern will be deleted: {example}',
            ['example' => 'DELNOTE 42 wheel']
        );
    }

    private function deleteBikeNote(User $user, int $bikeNumber, string $note): string
    {
        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            return $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber]);
        }

        $count = $this->noteRepository->deleteBikeNote(
            $bikeNumber,
            $note
        );

        if ($count == 0) {
            if ($note === "") {
                $message = $this->translator->trans(
                    'No notes found for bike {bikeNumber} to delete.',
                    ['bikeNumber' => $bikeNumber]
                );
            } else {
                $message = $this->translator->trans(
                    'No notes matching pattern {pattern} found for bike {bikeNumber} to delete.',
                    ['pattern' => $note, 'bikeNumber' => $bikeNumber]
                );
            }
        } else {
            if ($note === "") {
                $message = $this->translator->trans(
                    'All {count} notes for bike {bikeNumber} were deleted.',
                    ['bikeNumber' => $bikeNumber, 'count' => $count]
                );
                //TODO: notify other admins, not the one who deleted the note
//                notifyAdmins(
//                    _('All') . " " . sprintf(ngettext('%d note', '%d notes', $count), $count)
//                    . " " . _('for bike') . " " . $bikeNumber . " "
//                    . _('deleted by') . " " . $user->getUsername() . "."
//                );
            } else {
                $message = $this->translator->trans(
                    '{count} notes matching pattern "{pattern}" for bike {bikeNumber} were deleted.',
                    ['bikeNumber' => $bikeNumber, 'pattern' => $note, 'count' => $count]
                );
                //TODO: notify other admins, not the one who deleted the note
//                notifyAdmins(
//                    sprintf(ngettext('%d note', '%d notes', $count), $count) . " "
//                    . _('for bike') . " " . $bikeNumber . " "
//                    . _('matching') . " '" . $note . "' "
//                    . _('deleted by') . " " . $user->getUsername() . "."
//                );
            }
        }

        return $message;
    }

    private function deleteStandNote(User $user, string $standName, string $note): string
    {
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

        $standId = $standInfo["standId"];
        $reportedBy = $user->getUsername();

        $count = $this->noteRepository->deleteStandNote($standId, $note);

        if ($count === 0) {
            if ($note === "") {
                $message = $this->translator->trans(
                    'No notes found for stand {standName} to delete.',
                    ['standName' => $standName]
                );
            } else {
                $message = $this->translator->trans(
                    'No notes matching pattern {pattern} found on stand {standName} to delete.',
                    ['pattern' => $note, 'standName' => $standName]
                );
            }
        } else {
            if ($note === "") {
                $message = $this->translator->trans(
                    'All {count} notes for stand {standName} were deleted.',
                    ['standName' => $standName, 'count' => $count]
                );
                //TODO: notify other admins, not the one who deleted the note
//                notifyAdmins(
//                    _('All') . " "
//                    . sprintf(ngettext('%d note', '%d notes', $count), $count)
//                    . " " . _('on stand') . " " . $standName . " "
//                    . _('deleted by') . " " . $reportedBy . "."
//                );
            } else {
                $message = $this->translator->trans(
                    '{count} notes matching pattern "{pattern}" for stand {standName} were deleted.',
                    ['standName' => $standName, 'pattern' => $note, 'count' => $count]
                );
                //TODO: notify other admins, not the one who deleted the note
//                notifyAdmins(
//                    sprintf(ngettext('%d note', '%d notes', $count), $count)
//                    . " " . _('on stand') . " " . $standName . " "
//                    . _('matching') . " '" . $note . "' "
//                    . _('deleted by') . " "
//                    . $reportedBy . "."
//                );
            }
        }

        return $message;
    }
}
