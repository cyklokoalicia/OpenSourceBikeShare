<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StandController extends AbstractController
{
    public function __construct(
        private readonly StandRepository $standRepository,
        private readonly NoteRepository $noteRepository,
        private readonly bool $forceStack,
    ) {
    }

    #[Route('/api/stand', name: 'api_stand_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stands = $this->standRepository->findAll();

        return $this->json($stands);
    }

    #[Route(
        path: '/api/stand/{standName}/bike',
        name: 'api_stand_item',
        requirements: ['standName' => '\w+'],
        methods: ['GET'],
    )]
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

    #[Route(
        path: '/api/stand/{standName}/removeNote',
        name: 'api_stand_remove_note',
        requirements: ['standName' => '\w+'],
        methods: ['DELETE'],
    )]
    public function removeNote(
        $standName,
        NoteRepository $noteRepository,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stand = $this->standRepository->findItemByName($standName);

        if (empty($stand)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $pattern = $request->request->get('pattern');

        $deletedNotesCount = $noteRepository->deleteStandNote((int)$stand['standId'], $pattern);

        $response = [
            'message' => $deletedNotesCount . ' note(s) removed successfully',
            'error' => 0,
        ];

        return $this->json($response);
    }

    #[Route(
        path: '/api/stand/markers',
        name: 'api_stand_markers',
        methods: ['GET'],
        condition: "!request.headers.has('authorization')",
    )]
    public function markers(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $stands = $this->standRepository->findAllExtended($this->getUser()->getCity());

        return $this->json($stands);
    }

    #[Route(
        path: '/api/stand/markers',
        name: 'api_stand_markers_external',
        methods: ['GET'],
        condition: "request.headers.has('authorization')",
    )]
    public function apiMarkers(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_API');

        $stands = $this->standRepository->findAllExtended();

        return $this->json($stands);
    }
}
