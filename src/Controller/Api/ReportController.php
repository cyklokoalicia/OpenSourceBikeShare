<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\HistoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    /**
     * @Route("/report/daily", name="api_report_daily", methods={"GET"})
     */
    public function daily(
        HistoryRepository $historyRepository,
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

        $stats = $historyRepository->dailyStats();

        return $this->json($stats);
    }
    /**
     * @Route("/report/user/{year}", name="api_report_user", requirements: {'year' => '\d+'}, methods={"GET"})
     */
    public function user(
        HistoryRepository $historyRepository,
        LoggerInterface $logger,
        $year = null
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
