<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\BikeRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BikeController extends AbstractController
{
    /**
     * @Route("/api/bike", name="api_bike_index", methods={"GET"})
     */
    public function index(
        BikeRepository $bikeRepository,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        $bikes = $bikeRepository->findAll();

        return $this->json($bikes);
    }

    /**
     * @Route("/api/bike/{bikeNumber}", name="api_bike_item", methods={"GET"}, requirements: {"bikeNumber"="\d+"})
     */
    public function item(
        $bikeNumber,
        BikeRepository $bikeRepository,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $bikes = $bikeRepository->findItem((int)$bikeNumber);

        return $this->json($bikes);
    }

    /**
     * @Route("/bikeLastUsage/{bikeNumber}", name="api_bike_last_usage", methods={"GET"})
     */
    public function lastUsage(
        $bikeNumber,
        BikeRepository $bikeRepository,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        if (empty($bikeNumber) || !is_numeric($bikeNumber)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $bikes = $bikeRepository->findItemLastUsage((int)$bikeNumber);

        return $this->json($bikes);
    }
}
