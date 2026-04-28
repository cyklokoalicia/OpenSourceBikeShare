<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class WhereCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'WHERE';

    public function __construct(
        private readonly BikeRepository $bikeRepository,
        private readonly NoteRepository $noteRepository,
    ) {
    }

    public function __invoke(User $user, int $bikeNumber): TranslatableInterface
    {
        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            throw new ValidationException('bike.error.not_found', ['bikeNumber' => $bikeNumber]);
        }

        $notes = $this->noteRepository->findBikeNote($bikeNumber);
        $bikeCurrentUsage = $this->bikeRepository->findBikeCurrentUsage($bikeNumber);

        $note = $notes[0]['note'] ?? '';
        $standName = $bikeCurrentUsage['standName'];

        if (!is_null($standName)) {
            return new TranslatableMessage(
                'command.where.at_stand',
                ['bikeNumber' => $bikeNumber, 'standName' => $standName, 'note' => $note]
            );
        }

        return new TranslatableMessage(
            'command.where.in_use',
            [
                'bikeNumber' => $bikeNumber,
                'userName' => $bikeCurrentUsage['userName'],
                'phone' => $bikeCurrentUsage['number'],
                'note' => $note,
            ]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.where.help');
    }
}
