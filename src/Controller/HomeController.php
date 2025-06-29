<?php

namespace BikeShare\Controller;

use BikeShare\App\Configuration;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(
        Request $request,
        int $freeTimeHours,
        CreditSystemInterface $creditSystem,
        CityRepository $cityRepository,
        Configuration $configuration
    ): Response {

        //show stats for current year if it is end of the year
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
            'configuration' => $configuration,
            'cities' => $cityRepository->findAvailableCities(),
            'personalStatsYearUrl' => $personalStatsYearUrl,
            'freeTime' => $freeTimeHours,
            'creditSystem' => $creditSystem,
            'error' => $request->get('error', null),
        ]);
    }
}
