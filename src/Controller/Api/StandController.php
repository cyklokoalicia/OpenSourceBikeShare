<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StandController extends AbstractController
{
    /**
     * @Route("/api/stand", name="api_stand_index", methods={"GET"})
     */
    public function index(
        StandRepository $standRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stands = $standRepository->findAll();

        return $this->json($stands);
    }
}
