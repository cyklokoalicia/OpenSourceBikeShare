<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class InfoCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'INFO';

    private StandRepository $standRepository;

    public function __construct(
        TranslatorInterface $translator,
        StandRepository $bikeRepository
    ) {
        parent::__construct($translator);
        $this->standRepository = $bikeRepository;
    }

    public function __invoke(User $user, string $standName): string
    {
        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $standName)) {
            throw new ValidationException(
                $this->translator->trans(
                    'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                    ['standName' => $standName]
                )
            );
        }

        $standInfo = $this->standRepository->findItemByName($standName);

        if (empty($standInfo)) {
            throw new ValidationException(
                $this->translator->trans('Stand {standName} does not exist.', ['standName' => $standName])
            );
        }

        $standDescription = $standInfo["standDescription"];
        $standPhoto = $standInfo["standPhoto"];
        $standLat = round($standInfo["latitude"], 5);
        $standLong = round($standInfo["longitude"], 5);
        $message = $standName . " - " . $standDescription;
        if ($standLong && $standLat) {
            $message .= ", GPS: " . $standLat . "," . $standLong;
        }
        if ($standPhoto) {
            $message .= ", " . $standPhoto;
        }

        return $message;
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with stand name: {example}', ['example' => 'INFO RACKO']);
    }
}
