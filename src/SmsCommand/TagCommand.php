<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'TAG';
    protected const ARGUMENT_COUNT = 3;

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

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    protected function run(User $user, array $args): string
    {
        $note = urldecode(implode(' ', array_slice($args, self::ARGUMENT_COUNT - 1)));
        $standName = strtoupper(trim($args[1]));

        if (empty($note)) {
            return $this->translator->trans(
                'Empty tag for stand {standName} not saved, for deleting notes for all bikes on stand use UNTAG (for admins).',
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

        $this->noteRepository->addNoteToAllBikesOnStand(
            (int)$standInfo['standId'],
            $user->getUserId(),
            $note
        );

        //TODO: notify admins
//        notifyAdmins(
//            _('All bikes on stand') . " " . "$standName" . ' '
//            . _('tagged by') . " " . $user->getUsername() . " (" . $user->getNumber() . ")"
//            . _("with note:") . $note
//        );

        return $this->translator->trans(
            'All bikes on stand {standName} tagged with note.',
            ['standName' => $standName]
        );
    }

    protected function getValidationErrorMessage(): string
    {
        return $this->translator->trans(
            'with stand name and problem description: {example}',
            ['example' => 'TAG MAINSQUARE ' . $this->translator->trans('vandalism')]
        );
    }
}
