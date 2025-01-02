<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class FreeCommand implements SmsCommandInterface
{
    private const COMMAND_NAME = 'FREE';

    private TranslatorInterface $translator;
    private BikeRepository $bikeRepository;
    private StandRepository $standRepository;

    public function __construct(
        TranslatorInterface $translator,
        BikeRepository $bikeRepository,
        StandRepository $standRepository
    ) {
        $this->translator = $translator;
        $this->bikeRepository = $bikeRepository;
        $this->standRepository = $standRepository;
    }

    public function execute(User $user, array $args): string
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
            $message .= PHP_EOL . PHP_EOL . $this->translator->trans('Empty stands') . ": ";
            foreach ($freeStands as $row) {
                $message .= PHP_EOL . $row['standName'];
            }
        }

        return $message;
    }

    public static function getName(): string
    {
        return self::COMMAND_NAME;
    }
}
