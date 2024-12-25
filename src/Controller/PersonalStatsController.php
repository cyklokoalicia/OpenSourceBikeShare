<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Repository\StandRepository;
use BikeShare\Repository\StatsRepository;
use BikeShare\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PersonalStatsController extends AbstractController
{
    /**
     * @Route("/personalStats/year/{year}", name="personal_stats_year", methods={"GET"})
     */
    public function yearStats(
        $year,
        StatsRepository $statsRepository,
        StandRepository $standRepository,
        User $user
    ): Response {
        $userId = $user->findUserIdByNumber($this->getUser()->getUserIdentifier());
        $stats = $statsRepository->getUserStatsForYear((int)$userId, (int)$year);
        $stands = $standRepository->findAll();

        return $this->render('stats.html.twig', [
            'stats' => $stats,
            'stands' => $stands,
        ]);
    }
}
