<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\BikeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BikeController extends AbstractController
{
    /**
     * @Route("/api/bike", name="api_bike_index", methods={"GET"})
     */
    public function index(
        BikeRepository $bikeRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $bikes = $bikeRepository->findAll();

        return $this->json($bikes);
    }

    /**
     * @Route("/api/bike/{bikeNumber}", name="api_bike_item", methods={"GET"}, requirements: {"bikeNumber"="\d+"})
     */
    public function item(
        $bikeNumber,
        BikeRepository $bikeRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $bikes = [$bikeRepository->findItem((int)$bikeNumber)];

        return $this->json($bikes);
    }

    /**
     * @Route("/api/bikeLastUsage/{bikeNumber}", name="api_bike_last_usage", methods={"GET"})
     */
    public function lastUsage(
        $bikeNumber,
        BikeRepository $bikeRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $bikes = $bikeRepository->findItemLastUsage((int)$bikeNumber);

        return $this->json($bikes);
    }
}
