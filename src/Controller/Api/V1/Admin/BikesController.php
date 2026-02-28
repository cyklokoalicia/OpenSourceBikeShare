<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1\Admin;

use BikeShare\Enum\Action;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\NoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BikesController extends AbstractController
{
    public function __construct(private readonly BikeRepository $bikeRepository)
    {
    }

    public function index(): Response
    {
        $bikes = $this->bikeRepository->findAll();

        return $this->json($bikes);
    }

    public function item(
        string $bikeNumber
    ): Response {
        if ($bikeNumber === '' || !is_numeric($bikeNumber)) {
            return $this->json(['detail' => 'Invalid bike number'], Response::HTTP_BAD_REQUEST);
        }

        $bike = $this->bikeRepository->findItem((int)$bikeNumber);
        if (empty($bike)) {
            return $this->json(['detail' => 'Bike not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($bike);
    }

    public function lastUsage(
        string $bikeNumber
    ): Response {
        if ($bikeNumber === '' || !is_numeric($bikeNumber)) {
            return $this->json(['detail' => 'Invalid bike number'], Response::HTTP_BAD_REQUEST);
        }

        $bikes = $this->bikeRepository->findItemLastUsage((int)$bikeNumber);

        return $this->json($bikes);
    }

    public function setCode(
        string $bikeNumber,
        Request $request,
        HistoryRepository $historyRepository,
    ): Response {
        if ($bikeNumber === '' || !is_numeric($bikeNumber)) {
            return $this->json(['detail' => 'bikeNumber is required'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getPayload()->all();
        $code = $payload['code'] ?? null;
        if (!is_string($code) || !preg_match('/^\d{4}$/', $code)) {
            return $this->json([
                'detail' => 'Invalid code format. Use four digits.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $bikeNumberInt = (int)$bikeNumber;
        $formattedCode = sprintf('%04d', (int)$code);

        $this->bikeRepository->updateBikeCode($bikeNumberInt, (int)$code);

        $historyRepository->addItem(
            $this->getUser()->getUserId(),
            $bikeNumberInt,
            Action::CHANGE_CODE,
            $formattedCode,
        );

        return $this->json([
            'message' => sprintf('Bike %d code updated to %s.', $bikeNumberInt, $formattedCode),
            'error' => 0,
            'bikeNumber' => $bikeNumberInt,
            'code' => $formattedCode,
        ]);
    }

    public function removeNote(
        string $bikeNumber,
        NoteRepository $noteRepository,
        Request $request
    ): Response {
        if ($bikeNumber === '' || !is_numeric($bikeNumber)) {
            return $this->json(['detail' => 'bikeNumber is required'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getPayload()->all();
        $pattern = isset($payload['pattern']) && is_string($payload['pattern']) ? $payload['pattern'] : null;

        $deletedNotesCount = $noteRepository->deleteBikeNote((int)$bikeNumber, $pattern);

        return $this->json([
            'message' => $deletedNotesCount . ' note(s) removed successfully',
            'error' => 0,
        ]);
    }

    public function bikeTrip(
        string $bikeNumber,
        HistoryRepository $historyRepository
    ): Response {
        if ($bikeNumber === '' || !is_numeric($bikeNumber)) {
            return $this->json(['detail' => 'bikeNumber is required'], Response::HTTP_BAD_REQUEST);
        }

        $userTrip = $historyRepository->findBikeTrip(
            (int)$bikeNumber,
            new \DateTimeImmutable('-1 month')
        );

        return $this->json($userTrip);
    }
}
