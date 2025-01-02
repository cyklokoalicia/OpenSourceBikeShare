<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class LastCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'LAST';
    protected const ARGUMENT_COUNT = 2;
    protected const MIN_PRIVILEGES_LEVEL = 1;

    private BikeRepository $bikeRepository;

    public function __construct(
        TranslatorInterface $translator,
        BikeRepository $bikeRepository
    ) {
        parent::__construct($translator);
        $this->bikeRepository = $bikeRepository;
    }

    protected function run(User $user, array $args): string
    {
        $bikeNumber = (int)(trim($args[1]));

        $bikeInfo = $this->bikeRepository->findItem($bikeNumber);
        if (empty($bikeInfo)) {
            return $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber]);
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

    protected function getValidationErrorMessage(): string
    {
        return $this->translator->trans('with bike number: {example}', ['example' => 'LAST 42']);
    }
}
