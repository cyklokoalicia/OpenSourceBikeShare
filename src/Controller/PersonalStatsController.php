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
     * @Route("/personalStats/year/{year}", name="personal_stats_year", methods={"GET"}, requirements: {"year"="\d+"})
     */
    public function yearStats(
        StatsRepository $statsRepository,
        StandRepository $standRepository,
        User $user,
        $year = null
    ): Response {
        if (is_null($year)) {
            $year = (int)date('Y');
        } elseif (
            $year > (int)date('Y')
            || $year < 2010
        ) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $userId = $user->findUserIdByNumber($this->getUser()->getUserIdentifier());
        $stats = $statsRepository->getUserStatsForYear((int)$userId, (int)$year);
        $stands = $standRepository->findAll();

        return $this->render('stats.html.twig', [
            'stats' => $stats,
            'stands' => $stands,
            'year' => (int)$year,
        ]);
    }
}
