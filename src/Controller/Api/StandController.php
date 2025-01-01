<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\StandRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StandController extends AbstractController
{
    /**
     * @Route("/api/stand", name="api_stand_index", methods={"GET"})
     */
    public function index(
        StandRepository $standRepository,
        LoggerInterface $logger
    ): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        $bikes = $standRepository->findAll();

        return $this->json($bikes);
    }
}
