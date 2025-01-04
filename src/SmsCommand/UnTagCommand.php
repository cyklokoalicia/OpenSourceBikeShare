<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class UnTagCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'UNTAG';
    protected const ARGUMENT_COUNT = 2;

    private StandRepository $standRepository;
    private NoteRepository $noteRepository;

    public function __construct(
        TranslatorInterface $translator,
        StandRepository $standRepository,
        NoteRepository $noteRepository
    ) {
        parent::__construct($translator);
        $this->standRepository = $standRepository;
        $this->noteRepository = $noteRepository;
    }

    protected function run(User $user, array $args): string
    {
        $note = urldecode(implode(' ', array_slice($args, self::ARGUMENT_COUNT)));
        $standName = strtoupper(trim($args[1]));

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

        $count = $this->noteRepository->deleteNotesForAllBikesOnStand(
            (int)$standInfo['standId'],
            $note
        );

        if ($count === 0) {
            if ($note === "") {
                $message = $this->translator->trans(
                    'No bikes with notes found for stand {standName} to delete.',
                    ['standName' => $standName]
                );
            } else {
                $message = $this->translator->trans(
                    'No notes matching pattern "{pattern}" found for bikes on stand {standName} to delete.',
                    ['standName' => $standName, 'pattern' => $note]
                );
            }
        } else {
            if ($note == "") {
                $message = $this->translator->trans(
                    'All {count} notes for bikes on stand {$standName} were deleted.',
                    ['standName' => $standName, 'count' => $count]
                );
                //TODO: notify other admins, not the one who deleted the note
//                notifyAdmins(
//                    _('All') . " " . sprintf(ngettext('%d note', '%d notes', $count), $count)
//                    . " " . _('for bikes on stand') . " " . $standName . " "
//                    . _('deleted by') . " " . $user->getUsername() . "."
//                );
            } else {
                $message = $this->translator->trans(
                    '{count} notes matching pattern "{pattern}" for bikes on stand {standName} were deleted.',
                    ['pattern' => $note, 'standName' => $standName, 'count' => $count]
                );
                //TODO: notify other admins, not the one who deleted the note
//                notifyAdmins(
//                    sprintf(ngettext('%d note', '%d notes', $count), $count)
//                    . " " . _('for bikes on stand') . " " . $standName . " "
//                    . _('matching') . " '" . $note . "' "
//                    . _('deleted by') . " " . $user->getUsername() . "."
//                );
            }
        }

        return $message;
    }

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    protected function getValidationErrorMessage(): string
    {
        return $this->translator->trans(
            'with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand: {example}',
            ['example' => 'UNTAG MAINSQUARE ' . $this->translator->trans('vandalism')]
        );
    }
}
