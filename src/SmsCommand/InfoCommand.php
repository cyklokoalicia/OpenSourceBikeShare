<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class InfoCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'INFO';
    protected const ARGUMENT_COUNT = 2;

    private StandRepository $standRepository;

    public function __construct(
        TranslatorInterface $translator,
        StandRepository $bikeRepository
    ) {
        parent::__construct($translator);
        $this->standRepository = $bikeRepository;
    }

    protected function run(User $user, array $args): string
    {
        $stand = strtoupper(trim($args[1]));

        //SAFKO4ZRUSENY will not be recognized
        if (!preg_match("/^[A-Z]+[0-9]*$/", $stand)) {
            return $this->translator->trans(
                'Stand name {standName} has not been recognized. Stands are marked by CAPITALLETTERS.',
                ['standName' => $stand]
            );
        }

        $standInfo = $this->standRepository->findItemByName($stand);

        if (empty($standInfo)) {
            return $this->translator->trans('Stand {standName} does not exist.', ['standName' => $stand]);
        }

        $standDescription = $standInfo["standDescription"];
        $standPhoto = $standInfo["standPhoto"];
        $standLat = round($standInfo["latitude"], 5);
        $standLong = round($standInfo["longitude"], 5);
        $message = $stand . " - " . $standDescription;
        if ($standLong && $standLat) {
            $message .= ", GPS: " . $standLat . "," . $standLong;
        }
        if ($standPhoto) {
            $message .= ", " . $standPhoto;
        }

        return $message;
    }

    protected function getValidationErrorMessage(): string
    {
        return $this->translator->trans('with stand name: {example}', ['example' => 'INFO RACKO']);
    }
}
