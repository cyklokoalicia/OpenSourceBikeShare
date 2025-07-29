<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'LIST';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        TranslatorInterface $translator,
        private readonly StandRepository $standRepository,
        private readonly bool $forceStack = false
    ) {
        parent::__construct($translator);
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

        $stackTopBike = null;
        if ($this->forceStack) {
            $stackTopBike = $this->standRepository->findLastReturnedBikeOnStand((int)$standInfo['standId']);
        }

        $bikesOnStand = $this->standRepository->findBikesOnStand((int)$standInfo['standId']);

        if (count($bikesOnStand) === 0) {
            return $this->translator->trans('Stand {standName} is empty.', ['standName' => $standName]);
        }

        $listBikes = [];
        if ($this->forceStack && !is_null($stackTopBike)) {
            $listBikes[] = $stackTopBike . " " . $this->translator->trans('(first)');
        }

        foreach ($bikesOnStand as $bike) {
            if ($this->forceStack && $bike['bikeNum'] == $stackTopBike) {
                continue;
            }

            $listBikes[] = $bike['bikeNum'];
        }

        return $this->translator->trans(
            'Bikes on stand {standName}: {bikes}',
            ['standName' => $standName, 'bikes' => implode(', ', $listBikes)]
        );
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans('with stand name: {example}', ['example' => 'LIST MAINSQUARE']);
    }
}
