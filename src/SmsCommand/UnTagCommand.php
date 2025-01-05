<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class UnTagCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'UNTAG';

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

    public function __invoke(User $user, string $standName, ?string $pattern = null): string
    {
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

        $count = $this->noteRepository->deleteNotesForAllBikesOnStand(
            (int)$standInfo['standId'],
            $pattern
        );

        if ($count === 0) {
            if (is_null($pattern)) {
                throw new ValidationException(
                    $this->translator->trans(
                        'No bikes with notes found for stand {standName} to delete.',
                        ['standName' => $standName]
                    )
                );
            } else {
                throw new ValidationException(
                    $this->translator->trans(
                        'No notes matching pattern "{pattern}" found for bikes on stand {standName} to delete.',
                        ['standName' => $standName, 'pattern' => $pattern]
                    )
                );
            }
        } else {
            if (is_null($pattern)) {
                $message = $this->translator->trans(
                    'All {count} notes for bikes on stand {standName} were deleted.',
                    ['standName' => $standName, 'count' => $count]
                );
            } else {
                $message = $this->translator->trans(
                    '{count} notes matching pattern "{pattern}" for bikes on stand {standName} were deleted.',
                    ['pattern' => $pattern, 'standName' => $standName, 'count' => $count]
                );
            }
        }

        return $message;
    }

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    public function getHelpMessage(): string
    {
        return $this->translator->trans(
            'with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand: {example}',
            ['example' => 'UNTAG MAINSQUARE ' . $this->translator->trans('vandalism')]
        );
    }
}
