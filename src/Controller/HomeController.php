<?php

namespace BikeShare\Controller;

use BikeShare\App\Configuration;
use BikeShare\Credit\CreditSystemInterface;
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
        Configuration $configuration
    ): Response {

        //show stats for current year if it is end of the year
        $currentData = new \DateTimeImmutable();
        if ($currentData->format('z') > 350) {
            $personalStatsYearUrl = $this->generateUrl(
                'personal_stats_year',
                ['year' => $currentData->format('Y')]
            );
        } elseif ($currentData->format('z') < 30) {
            $personalStatsYearUrl = $this->generateUrl(
                'personal_stats_year',
                ['year' => $currentData->format('Y') - 1]
            );
        } else {
            $personalStatsYearUrl = null;
        }

        return $this->render('index.html.twig', [
            'configuration' => $configuration,
            'personalStatsYearUrl' => $personalStatsYearUrl,
            'freeTime' => $freeTimeHours,
            'creditSystem' => $creditSystem,
            'error' => $request->get('error', null),
        ]);
    }
}
