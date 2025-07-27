<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @Route("/api/bike/{bikeNumber}/lastUsage", name="api_bike_last_usage", methods={"GET"})
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
     * @Route("/api/bike/{bikeNumber}/rent", name="api_bike_rent", methods={"POST"})
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
}
