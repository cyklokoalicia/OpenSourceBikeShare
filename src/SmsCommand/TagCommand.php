<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class TagCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'TAG';

    public function __construct(
        private readonly StandRepository $standRepository,
        private readonly NoteRepository $noteRepository
    ) {
    }

    public function __invoke(User $user, string $standName, ?string $note = null): TranslatableInterface
    {
        if (empty($note)) {
            throw new ValidationException('command.tag.error.empty_tag', ['standName' => $standName]);
        }

        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            throw new ValidationException('stand.error.unrecognized', ['standName' => $standName]);
        }

        $standInfo = $this->standRepository->findItemByName($standName);
        if (empty($standInfo)) {
            throw new ValidationException('stand.error.not_found', ['standName' => $standName]);
        }

        $this->noteRepository->addNoteToAllBikesOnStand(
            (int)$standInfo['standId'],
            $user->getUserId(),
            $note
        );

        return new TranslatableMessage(
            'command.tag.success',
            ['standName' => $standName, 'note' => $note]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.tag.help');
    }
}
