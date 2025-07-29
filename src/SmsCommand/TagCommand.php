<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class TagCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'TAG';

    public function __construct(
        TranslatorInterface $translator,
        private StandRepository $standRepository,
        private NoteRepository $noteRepository
    ) {
        parent::__construct($translator);
    }

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    public function __invoke(User $user, string $standName, ?string $note = null): string
    {
        if (empty($note)) {
            throw new ValidationException(
                $this->translator->trans(
                    'Empty tag for stand {standName} not saved, for deleting notes for all bikes on stand use UNTAG (for admins).',
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

        $this->noteRepository->addNoteToAllBikesOnStand(
            (int)$standInfo['standId'],
            $user->getUserId(),
            $note
        );

        return $this->translator->trans(
            'All bikes on stand {standName} tagged with note "{note}".',
            ['standName' => $standName, 'note' => $note]
        );
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans(
            'with stand name and problem description: {example}',
            ['example' => 'TAG MAINSQUARE ' . $this->translator->trans('vandalism')]
        );
    }
}
