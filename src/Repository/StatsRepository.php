<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Psr\Log\LoggerInterface;

class StatsRepository
{
    private DbInterface $db;
    private LoggerInterface $logger;

    public function __construct(
        DbInterface $db,
        LoggerInterface $logger
    ) {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function getUserStatsForYear(int $userId, int $year): array
    {
        $currentYear = (int)date('Y');
        if ($year > $currentYear || $year < 2000) {
            throw new \InvalidArgumentException('Invalid year provided');
        }

        $stats = [
            'rental_count' => 0,
            'longest_rental_duration' => 0,
            'shortest_rental_duration' => PHP_INT_MAX,
            'total_rental_duration' => 0,
            'average_rental_duration' => 0,
            'bikes_rented' => [],
            'rent_station' => [],
            'return_station' => [],
            'rent_period' => [
                'part_of_day' => [],
                'day_of_week' => [],
                'month' => [],
            ],
        ];

        $query = 'SELECT 
                userId,
                bikeNum,
                time,
                action,
                parameter
              FROM history
              WHERE userId = :userId
                AND bikeNum != 0
                AND action NOT LIKE \'%CREDIT%\'
                AND YEAR(time) = :year
              ORDER BY time ASC';
        $history = $this->db->query($query, ['userId' => $userId, 'year' => $year])->fetchAllAssoc();

        $rentHistory = [];
        $returnHistory = [];
        foreach ($history as $item) {
            $date = new \DateTimeImmutable($item['time']);
            if ($item['action'] === 'RENT' || $item['action'] === 'FORCERENT') {
                $rentHistory[$item['bikeNum']] = $item;
                $stats['rental_count']++;
                if (isset($returnHistory[$item['bikeNum']])) {
                    $stats['rent_station'][$returnHistory[$item['bikeNum']]['parameter']] =
                        ($stats['rent_station'][$returnHistory[$item['bikeNum']]['parameter']] ?? 0) + 1;
                }
                $stats['bikes_rented'][$item['bikeNum']] = ($stats['bikes_rented'][$item['bikeNum']] ?? 0) + 1;
                $partOfDay = $date->format('H') < 6 ? 'Night' : ($date->format('H') < 12 ?
                    'Morning' : ($date->format('H') < 18 ? 'Day' : 'Evening'));
                $stats['rent_period']['part_of_day'][$partOfDay] =
                    ($stats['rent_period']['part_of_day'][$partOfDay] ?? 0) + 1;
                $stats['rent_period']['day_of_week'][$date->format('l')] =
                    ($stats['rent_period']['day_of_week'][$date->format('l')] ?? 0) + 1;
                $stats['rent_period']['month'][$date->format('F')] =
                    ($stats['rent_period']['month'][$date->format('F')] ?? 0) + 1;
            } elseif ($item['action'] === 'RETURN' || $item['action'] === 'FORCERETURN') {
                $returnHistory[$item['bikeNum']] = $item;
                $stats['return_station'][$item['parameter']] = ($stats['return_station'][$item['parameter']] ?? 0) + 1;
                $rentDuration = strtotime($item['time'])
                    - strtotime($rentHistory[$item['bikeNum']]['time'] ?? $item['time']);
                if ($rentDuration > 3600 * 24 * 7) {
                    $this->logger->warning(
                        'Too long rental duration',
                        [
                            'rentDuration' => $rentDuration,
                            'rentHistory' => $rentHistory[$item['bikeNum']],
                            'returnHistory' => $item
                        ]
                    );
                }
                $stats['total_rental_duration'] += $rentDuration;
                $stats['longest_rental_duration'] = max($stats['longest_rental_duration'], $rentDuration);
                $stats['shortest_rental_duration'] = min($stats['shortest_rental_duration'], $rentDuration);
                if (!empty($rentHistory[$item['bikeNum']])) {
                    $stats['return_station'][$rentHistory[$item['bikeNum']]['parameter']] =
                        ($stats['return_station'][$rentHistory[$item['bikeNum']]['parameter']] ?? 0) + 1;
                }
                unset($rentHistory[$item['bikeNum']]);
            } elseif ($item['action'] === 'REVERT') {
                unset($rentHistory[$item['bikeNum']]);
                unset($returnHistory[$item['bikeNum']]);
            } else {
                $this->logger->warning('Unknown action', ['action' => $item['action'], 'item' => $item]);
            }
        }

        if ($stats['rental_count'] > 1) {
            $stats['average_rental_duration'] = $stats['total_rental_duration'] / $stats['rental_count'] ?? 0;
        }
        $stats['unique_bikes_rented'] = count($stats['bikes_rented']);
        arsort($stats['bikes_rented']);
        $stats['most_popular_bike'] = array_key_first($stats['bikes_rented']);
        arsort($stats['return_station']);
        $stats['most_popular_return_station'] = array_key_first($stats['return_station']);
        arsort($stats['rent_station']);
        $stats['most_popular_rent_station'] = array_key_first($stats['rent_station']);
        arsort($stats['rent_period']['part_of_day']);
        $stats['most_popular_part_of_day'] = array_key_first($stats['rent_period']['part_of_day']);
        arsort($stats['rent_period']['day_of_week']);
        $stats['most_popular_day_of_week'] = array_key_first($stats['rent_period']['day_of_week']);
        arsort($stats['rent_period']['month']);
        $stats['most_popular_month'] = array_key_first($stats['rent_period']['month']);

        if ($stats['shortest_rental_duration'] === PHP_INT_MAX) {
            $stats['shortest_rental_duration'] = 0;
        }

        return $stats;
    }
}
