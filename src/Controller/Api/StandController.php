<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StandController extends AbstractController
{
    private StandRepository $standRepository;
    private NoteRepository $noteRepository;
    private bool $forceStack;

    public function __construct(
        StandRepository $standRepository,
        NoteRepository $noteRepository,
        bool $forceStack
    ) {
        $this->standRepository = $standRepository;
        $this->noteRepository = $noteRepository;
        $this->forceStack = $forceStack;
    }


    /**
     * @Route("/api/stand", name="api_stand_index", methods={"GET"})
     */
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stands = $this->standRepository->findAll();

        return $this->json($stands);
    }

    /**
     * @Route("/api/stand/{standName}/bike", name="api_stand_item", methods={"GET"}, requirements: {"standName"="\w+"})
     */
    public function bike(
        string $standName
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $standInfo = $this->standRepository->findItemByName($standName);

        if (empty($standInfo)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
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
}
