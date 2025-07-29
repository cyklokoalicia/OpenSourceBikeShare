<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class DelNoteCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'DELNOTE';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        TranslatorInterface $translator,
        private BikeRepository $bikeRepository,
        private StandRepository $standRepository,
        private NoteRepository $noteRepository
    ) {
        parent::__construct($translator);
        $this->translator = $translator;
    }

    public function __invoke(
        User $user,
        ?int $bikeNumber = null,
        ?string $standName = null,
        ?string $pattern = null
    ): string {
        if (!is_null($bikeNumber)) {
            return $this->deleteBikeNote($user, $bikeNumber, $pattern);
        } elseif (!is_null($standName)) {
            return $this->deleteStandNote($user, $standName, $pattern);
        } else {
            throw new ValidationException($this->getHelpMessage());
        }
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans(
            'with bike number and optional pattern. All messages or notes matching pattern will be deleted: {example}',
            ['example' => 'DELNOTE 42 wheel']
        );
    }

    private function deleteBikeNote(User $user, int $bikeNumber, ?string $pattern): string
    {
        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            throw new ValidationException(
                $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber])
            );
        }

        $count = $this->noteRepository->deleteBikeNote(
            $bikeNumber,
            $pattern
        );

        if ($count == 0) {
            if (is_null($pattern)) {
                throw new ValidationException(
                    $this->translator->trans(
                        'No notes found for bike {bikeNumber} to delete.',
                        ['bikeNumber' => $bikeNumber]
                    )
                );
            } else {
                throw new ValidationException(
                    $this->translator->trans(
                        'No notes matching pattern {pattern} found for bike {bikeNumber} to delete.',
                        ['pattern' => $pattern, 'bikeNumber' => $bikeNumber]
                    )
                );
            }
        } else {
            if (is_null($pattern)) {
                $message = $this->translator->trans(
                    'All {count} notes for bike {bikeNumber} were deleted.',
                    ['bikeNumber' => $bikeNumber, 'count' => $count]
                );
            } else {
                $message = $this->translator->trans(
                    '{count} notes matching pattern "{pattern}" for bike {bikeNumber} were deleted.',
                    ['bikeNumber' => $bikeNumber, 'pattern' => $pattern, 'count' => $count]
                );
            }
        }

        return $message;
    }

    private function deleteStandNote(User $user, string $standName, ?string $pattern): string
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

        $count = $this->noteRepository->deleteStandNote((int)$standInfo["standId"], $pattern);

        if ($count === 0) {
            if (is_null($pattern)) {
                throw new ValidationException(
                    $this->translator->trans(
                        'No notes found for stand {standName} to delete.',
                        ['standName' => $standName]
                    )
                );
            } else {
                throw new ValidationException(
                    $this->translator->trans(
                        'No notes matching pattern {pattern} found on stand {standName} to delete.',
                        ['pattern' => $pattern, 'standName' => $standName]
                    )
                );
            }
        } else {
            if (is_null($pattern)) {
                $message = $this->translator->trans(
                    'All {count} notes for stand {standName} were deleted.',
                    ['standName' => $standName, 'count' => $count]
                );
            } else {
                $message = $this->translator->trans(
                    '{count} notes matching pattern "{pattern}" for stand {standName} were deleted.',
                    ['standName' => $standName, 'pattern' => $pattern, 'count' => $count]
                );
            }
        }

        return $message;
    }
}
