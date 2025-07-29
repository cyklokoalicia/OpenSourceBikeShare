<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class WhereCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'WHERE';

    public function __construct(
        TranslatorInterface $translator,
        private readonly BikeRepository $bikeRepository,
        private readonly NoteRepository $noteRepository,
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user, int $bikeNumber): string
    {
        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            throw new ValidationException(
                $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber])
            );
        }

        $notes = $this->noteRepository->findBikeNote($bikeNumber);
        $bikeCurrentUsage = $this->bikeRepository->findBikeCurrentUsage($bikeNumber);

        $phone = $bikeCurrentUsage["number"];
        $userName = $bikeCurrentUsage["userName"];
        $standName = $bikeCurrentUsage["standName"];
        $note = $notes[0]['note'] ?? '';

        if (!is_null($standName)) {
            $message = $this->translator->trans(
                'Bike {bikeNumber} is at stand {standName}. {note}',
                ['bikeNumber' => $bikeNumber, 'standName' => $standName, 'note' => $note]
            );
        } else {
            $message = $this->translator->trans(
                'Bike {bikeNumber} is rented by {userName} (+{phone}). {note}',
                [
                    'bikeNumber' => $bikeNumber,
                    'userName' => $userName,
                    'phone' => $phone,
                    'note' => $note
                ]
            );
        }

        return $message;
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'WHERE 42']);
    }
}
