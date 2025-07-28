<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\NoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BikeController extends AbstractController
{
    private BikeRepository $bikeRepository;

    public function __construct(BikeRepository $bikeRepository)
    {
        $this->bikeRepository = $bikeRepository;
    }

    /**
     * @Route("/api/bike", name="api_bike_index", methods={"GET"})
     */
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $bikes = $this->bikeRepository->findAll();

        return $this->json($bikes);
    }

    /**
     * @Route("/api/bike/{bikeNumber}", name="api_bike_item", methods={"GET"}, requirements: {"bikeNumber"="\d+"})
     */
    public function item(
        $bikeNumber
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $bikes = [$this->bikeRepository->findItem((int)$bikeNumber)];

        return $this->json($bikes);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/lastUsage", name="api_bike_last_usage", methods={"GET"}, requirements: {"bikeNumber"="\d+"})
     */
    public function lastUsage(
        $bikeNumber
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $bikes = $this->bikeRepository->findItemLastUsage((int)$bikeNumber);

        return $this->json($bikes);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/rent", name="api_bike_rent", methods={"PUT"}, requirements: {"bikeNumber"="\d+"})
     */
    public function rentBike(
        $bikeNumber,
        RentSystemFactory $rentSystemFactory
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $response = $rentSystemFactory->getRentSystem('web')->rentBike(
            $this->getUser()->getUserId(),
            (int)$bikeNumber
        );

        return $this->json($response);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/return", name="api_bike_return", methods={"PUT"}, requirements: {"standName"="\w+", "bikeNumber"="\d+"})
     */
    public function returnBike(
        $bikeNumber,
        $standName,
        Request $request,
        RentSystemFactory $rentSystemFactory
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $response = $rentSystemFactory->getRentSystem('web')->returnBike(
            $this->getUser()->getUserId(),
            (int)$bikeNumber,
            $standName,
            $request->request->get('note', ''),
        );

        return $this->json($response);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/forceRent", name="api_bike_force_rent", methods={"PUT"}, requirements: {"bikeNumber"="\d+"})
     */
    public function forceRentBike(
        $bikeNumber,
        RentSystemFactory $rentSystemFactory
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');;

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $response = $rentSystemFactory->getRentSystem('web')->rentBike(
            $this->getUser()->getUserId(),
            (int)$bikeNumber,
            true // Force rent
        );

        return $this->json($response);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/forceReturn", name="api_bike_force_return", methods={"PUT"}, requirements: {"standName"="\w+", "bikeNumber"="\d+"})
     */
    public function forceReturnBike(
        $bikeNumber,
        $standName,
        Request $request,
        RentSystemFactory $rentSystemFactory
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $response = $rentSystemFactory->getRentSystem('web')->returnBike(
            $this->getUser()->getUserId(),
            (int)$bikeNumber,
            $standName,
            $request->request->get('note', ''),
            true // Force return
        );

        return $this->json($response);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/revert", name="api_bike_revert", methods={"PUT"}, requirements: {"bikeNumber"="\d+"})
     */
    public function revertBike(
        $bikeNumber,
        RentSystemFactory $rentSystemFactory
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $response = $rentSystemFactory->getRentSystem('web')->revertBike(
            $this->getUser()->getUserId(),
            (int)$bikeNumber,
        );

        return $this->json($response);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/removeNote", name="api_bike_remove_note", methods={"DELETE"}, requirements: {"bikeNumber"="\d+"})
     */
    public function removeNote(
        $bikeNumber,
        NoteRepository $noteRepository,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $pattern = $request->request->get('pattern');

        $deletedNotesCount = $noteRepository->deleteBikeNote((int)$bikeNumber, $pattern);

        $response = [
            'message' => $deletedNotesCount . ' note(s) removed successfully',
            'error' => 0,
        ];

        return $this->json($response);
    }

    /**
     * @Route("/api/bike/{bikeNumber}/trip", name="api_bike_trip", methods={"GET"}, requirements: {"bikeNumber"="\d+"})
     */
    public function bikeTrip(
        $bikeNumber,
        HistoryRepository $historyRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $userTrip = $historyRepository->findBikeTrip(
            (int)$bikeNumber,
            new \DateTimeImmutable('-1 month')
        );

        return $this->json($userTrip);
    }
}
