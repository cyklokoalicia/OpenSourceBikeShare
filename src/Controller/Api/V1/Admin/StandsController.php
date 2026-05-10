<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1\Admin;

use BikeShare\Enum\StandStatus;
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
        $stands = $this->standRepository->findAll(StandStatus::cases());

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

    public function itemById(int $standId): Response
    {
        $stand = $this->standRepository->findItem($standId);
        if (empty($stand)) {
            return $this->json(['detail' => 'Stand not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($stand);
    }

    public function update(int $standId, Request $request): Response
    {
        $stand = $this->standRepository->findItem($standId);
        if (empty($stand)) {
            return $this->json(['detail' => 'Stand not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = $request->getPayload()->all();
        $status = StandStatus::tryFrom($payload['status'] ?? '');
        if ($status === null) {
            return $this->json(['detail' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $previousStatus = $stand['status'];
        $this->standRepository->updateStatus($standId, $status);

        return $this->json([
            'message' => sprintf(
                'Stand %s status changed: %s -> %s',
                $stand['standName'],
                $previousStatus,
                $status->value
            ),
            'status' => $status->value,
        ]);
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
