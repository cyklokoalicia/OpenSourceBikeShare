<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Enum\Action;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CodeCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'CODE';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        TranslatorInterface $translator,
        private readonly BikeRepository $bikeRepository,
        private readonly HistoryRepository $historyRepository,
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user, int $bikeNumber, string $code): string
    {
        if ($bikeNumber <= 0) {
            throw new ValidationException($this->translator->trans('Invalid bike number'));
        }

        if (!preg_match('/^\d{4}$/', $code)) {
            throw new ValidationException($this->translator->trans('Invalid code format. Use four digits.'));
        }

        $formattedCode = sprintf('%04d', (int) $code);

        $this->bikeRepository->updateBikeCode($bikeNumber, (int) $formattedCode);

        $this->historyRepository->addItem(
            $user->getUserId(),
            $bikeNumber,
            Action::CHANGE_CODE,
            $formattedCode,
        );

        return $this->translator->trans(
            'Bike {bikeNumber} code updated to {code}.',
            [
                'bikeNumber' => $bikeNumber,
                'code' => $formattedCode,
            ]
        );
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number and code: {example}', ['example' => 'CODE 42 1234']);
    }
}
