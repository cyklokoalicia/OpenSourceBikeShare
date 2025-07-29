<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class FreeCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'FREE';

    public function __construct(
        TranslatorInterface $translator,
        private readonly BikeRepository $bikeRepository,
        private readonly StandRepository $standRepository
    ) {
        parent::__construct($translator);
    }

    public function __invoke(User $user): string
    {
        $freeBikes = $this->bikeRepository->findFreeBikes();

        if (empty($freeBikes)) {
            return $this->translator->trans('No free bikes.');
        }

        $message = $this->translator->trans('Free bikes counts') . ':';
        foreach ($freeBikes as $row) {
            $message .= PHP_EOL . $row['standName'] . ': ' . $row["bikeCount"];
        }

        $freeStands = $this->standRepository->findFreeStands();

        if (!empty($freeStands)) {
            $message .= PHP_EOL . PHP_EOL . $this->translator->trans('Empty stands') . ":";
            foreach ($freeStands as $row) {
                $message .= PHP_EOL . $row['standName'];
            }
        }

        return $message;
    }

    public function getHelpMessage(): string
    {
        return '';
    }
}
