<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Enum\Action;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class CodeCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'CODE';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        private readonly BikeRepository $bikeRepository,
        private readonly HistoryRepository $historyRepository,
    ) {
    }

    public function __invoke(User $user, int $bikeNumber, string $code): TranslatableInterface
    {
        if ($bikeNumber <= 0) {
            throw new ValidationException('bike.error.invalid_number');
        }

        if (!preg_match('/^\d{4}$/', $code)) {
            throw new ValidationException('bike.error.invalid_code_format');
        }

        $formattedCode = sprintf('%04d', (int) $code);

        $this->bikeRepository->updateBikeCode($bikeNumber, (int) $formattedCode);

        $this->historyRepository->addItem(
            $user->getUserId(),
            $bikeNumber,
            Action::CHANGE_CODE,
            $formattedCode,
        );

        return new TranslatableMessage(
            'command.code.success',
            ['bikeNumber' => $bikeNumber, 'code' => $formattedCode]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.code.help');
    }
}
