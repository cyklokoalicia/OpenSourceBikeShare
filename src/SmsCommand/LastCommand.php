<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Enum\Action;
use BikeShare\Repository\BikeRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class LastCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'LAST';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        private readonly BikeRepository $bikeRepository
    ) {
    }

    public function __invoke(User $user, int $bikeNumber): TranslatableInterface
    {
        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            throw new ValidationException('bike.error.not_found', ['bikeNumber' => $bikeNumber]);
        }

        $lastUsage = $this->bikeRepository->findItemLastUsage($bikeNumber);

        $historyParts = [];
        foreach ($lastUsage['history'] as $row) {
            if (!in_array($row['action'], [Action::RETURN->value, Action::RENT->value, Action::REVERT->value])) {
                continue;
            }

            if (!is_null($standName = $row['standName'])) {
                if ($row['action'] == Action::REVERT->value) {
                    $historyParts[] = '*';
                }
                $historyParts[] = $standName;
            } else {
                $historyParts[] = $row['userName'] . '(' . $row['parameter'] . ')';
            }
        }

        return new TranslatableMessage(
            'command.last.message',
            ['bikeNumber' => $bikeNumber, 'history' => implode(',', $historyParts)]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.last.help');
    }
}
