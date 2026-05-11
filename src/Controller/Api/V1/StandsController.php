<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\Enum\StandStatus;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class StandsController extends AbstractController
{
    public function __construct(
        private readonly StandRepository $standRepository,
        private readonly NoteRepository $noteRepository,
        private readonly bool $forceStack,
    ) {
    }

    public function bike(
        string $standName
    ): Response {
        $standInfo = $this->standRepository->findItemByName($standName);

        if (empty($standInfo)) {
            return $this->json(['detail' => 'Stand not found'], Response::HTTP_NOT_FOUND);
        }

        $stackTopBike = false;
        if ($this->forceStack) {
            $stackTopBike = $this->standRepository->findLastReturnedBikeOnStand((int)$standInfo['standId']);
        }

        $bikesOnStand = $this->standRepository->findBikesOnStand((int)$standInfo['standId']);
        foreach ($bikesOnStand as &$bike) {
            $notes = $this->noteRepository->findBikeNote((int)$bike['bikeNum']);
            $notes = array_map(
                static fn(array $note) => $note['note'],
                $notes
            );
            $bike['notes'] = implode('; ', $notes);
        }

        unset($bike);

        return $this->json(
            [
                'stackTopBike' => $stackTopBike,
                'bikesOnStand' => $bikesOnStand,
            ]
        );
    }

    public function markers(): Response
    {
        $statuses = [StandStatus::ACTIVE, StandStatus::TECHNICAL];
        if ($this->isGranted('ROLE_ADMIN')) {
            $statuses[] = StandStatus::HIDDEN;
        }

        $stands = $this->standRepository->findAll($statuses);
        $userCity = $this->getUser()->getCity();
        $stands = array_values(array_filter(
            $stands,
            fn(array $stand) => $stand['city'] === $userCity,
        ));

        return $this->json($stands);
    }
}
