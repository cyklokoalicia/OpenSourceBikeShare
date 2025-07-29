<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class LastCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'LAST';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        TranslatorInterface $translator,
        private readonly BikeRepository $bikeRepository
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

        $lastUsage = $this->bikeRepository->findItemLastUsage($bikeNumber);

        $historyInfo = [];
        $historyInfo[] = "B.$bikeNumber:";
        foreach ($lastUsage['history'] as $row) {
            if (!in_array($row['action'], ['RETURN','RENT','REVERT'])) {
                continue;
            }

            if (!is_null($standName = $row["standName"])) {
                if ($row["action"] == "REVERT") {
                    $historyInfo[] = "*";
                }

                $historyInfo[] = $standName;
            } else {
                $historyInfo[] = $row["userName"] . "(" . $row["parameter"] . ")";
            }
        }

        return implode(',', $historyInfo);
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'LAST 42']);
    }
}
