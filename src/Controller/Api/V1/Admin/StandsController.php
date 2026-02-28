<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1\Admin;

use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StandsController extends AbstractController
{
    public function __construct(
        private readonly StandRepository $standRepository,
    ) {
    }

    public function index(): Response
    {
        $stands = $this->standRepository->findAll();

        return $this->json($stands);
    }

    public function item(string $standName): Response
    {
        $stand = $this->standRepository->findItemByName($standName);
        if (empty($stand)) {
            return $this->json(['detail' => 'Stand not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($stand);
    }

    public function removeNote(
        string $standName,
        NoteRepository $noteRepository,
        Request $request
    ): Response {
        $stand = $this->standRepository->findItemByName($standName);

        if (empty($stand)) {
            return $this->json(['detail' => 'Stand not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->getPayload()->all();
        $pattern = isset($payload['pattern']) && is_string($payload['pattern']) ? $payload['pattern'] : null;

        $deletedNotesCount = $noteRepository->deleteStandNote((int)$stand['standId'], $pattern);

        return $this->json([
            'message' => $deletedNotesCount . ' note(s) removed successfully',
        ]);
    }
}
