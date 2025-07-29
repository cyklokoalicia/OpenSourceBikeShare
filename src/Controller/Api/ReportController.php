<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\HistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    /**
     * @Route("/api/report/daily", name="api_report_daily", methods={"GET"})
     */
    public function daily(
        HistoryRepository $historyRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $stats = $historyRepository->dailyStats();

        return $this->json($stats);
    }

    /**
     * @Route("/api/report/user/{year}", name="api_report_user", requirements: {'year' => '\d+'}, methods={"GET"})
     */
    public function user(
        HistoryRepository $historyRepository,
        $year = null
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (is_null($year)) {
            $year = (int)date('Y');
        } elseif (
            $year > (int)date('Y')
            || $year < 2010
        ) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $stats = $historyRepository->userStats((int)$year);

        return $this->json($stats);
    }
}
