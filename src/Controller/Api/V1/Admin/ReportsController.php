<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1\Admin;

use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

class ReportsController extends AbstractController
{
    public function daily(
        HistoryRepository $historyRepository
    ): Response {
        $stats = $historyRepository->dailyStats();

        return $this->json($stats);
    }

    public function user(
        HistoryRepository $historyRepository,
        ClockInterface $clock,
        $year = null,
    ): Response {
        if (is_null($year)) {
            $year = (int)$clock->now()->format('Y');
        } elseif (
            $year > (int)$clock->now()->format('Y')
            || $year < 2010
        ) {
            return $this->json(['detail' => 'Invalid year'], Response::HTTP_BAD_REQUEST);
        }

        $stats = $historyRepository->userStats((int)$year);

        return $this->json($stats);
    }

    public function inactiveBikes(
        BikeRepository $bikeRepository,
        ClockInterface $clock
    ): Response {
        $now = $clock->now();
        $weekThreshold = $now->sub(new \DateInterval('P7D'));

        $inactiveBikes = $bikeRepository->findInactiveBikes($weekThreshold);

        $result = [];
        foreach ($inactiveBikes as $bike) {
            $lastMoveTime = new \DateTimeImmutable((string)$bike['lastMoveTime']);
            $inactiveDays = (int)$lastMoveTime->diff($now)->days;

            $result[] = [
                'bikeNum' => (int)$bike['bikeNum'],
                'standName' => (string)$bike['standName'],
                'lastMoveTime' => $lastMoveTime->format('Y-m-d H:i:s'),
                'inactiveDays' => $inactiveDays,
            ];
        }

        return $this->json($result);
    }
}
