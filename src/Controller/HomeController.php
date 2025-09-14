<?php

namespace BikeShare\Controller;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\CityRepository;
use BikeShare\Repository\UserSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(
        Request $request,
        int $freeTimeHours,
        int $systemZoom,
        string $systemRules,
        CreditSystemInterface $creditSystem,
        CityRepository $cityRepository,
        UserSettingsRepository $userSettingsRepository,
    ): Response {

        //show stats for the current year if it is the end of the year
        $currentDate = new \DateTimeImmutable();
        if ($currentDate->format('z') > 350) {
            $personalStatsYearUrl = $this->generateUrl(
                'personal_stats_year',
                ['year' => $currentDate->format('Y')]
            );
        } elseif ($currentDate->format('z') < 30) {
            $personalStatsYearUrl = $this->generateUrl(
                'personal_stats_year',
                ['year' => $currentDate->format('Y') - 1]
            );
        } else {
            $personalStatsYearUrl = null;
        }

        return $this->render('index.html.twig', [
            'systemZoom' => $systemZoom,
            'systemRules' => $systemRules,
            'cities' => $cityRepository->findAvailableCities(),
            'userSettings' => $userSettingsRepository->findByUserId($this->getUser()->getUserId()),
            'personalStatsYearUrl' => $personalStatsYearUrl,
            'freeTime' => $freeTimeHours,
            'creditSystem' => $creditSystem,
            'error' => $request->get('error', null),
        ]);
    }
}
