<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Repository\StandRepository;
use BikeShare\Repository\StatsRepository;
use BikeShare\User\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PersonalStatsController extends AbstractController
{
    #[Route(
        path: '/personalStats/year/{year}',
        name: 'personal_stats_year',
        requirements: ['year' => '\d+'],
        methods: ['GET'],
    )]
    public function yearStats(
        StatsRepository $statsRepository,
        StandRepository $standRepository,
        ClockInterface $clock,
        User $user,
        $year = null,
    ): Response {
        if (is_null($year)) {
            $year = (int)$clock->now()->format('Y');
        } elseif (
            $year > (int)$clock->now()->format('Y')
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
