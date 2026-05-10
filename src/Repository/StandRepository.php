<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
use BikeShare\Enum\StandStatus;

class StandRepository
{
    public function __construct(
        private readonly DbInterface $db,
        private readonly CityRepository $cityRepository,
    ) {
    }

    /**
     * @param StandStatus[] $statuses Statuses to include. Defaults to publicly visible (active + technical).
     */
    public function findAll(array $statuses = []): array
    {
        if (empty($statuses)) {
            $statuses = [StandStatus::ACTIVE, StandStatus::TECHNICAL];
        }

        $cityParams = $this->buildCityParams();
        if ($cityParams === null) {
            return [];
        }

        $statusParams = [];
        $statusPlaceholders = [];
        foreach ($statuses as $i => $status) {
            $key = 'status' . $i;
            $statusParams[$key] = $status->value;
            $statusPlaceholders[] = ':' . $key;
        }

        $cityPlaceholders = array_map(fn(string $k) => ':' . $k, array_keys($cityParams));

        $result = $this->db->query(
            "SELECT
                standId,
                count(bikeNum) AS bikeCount,
                standName,
                standDescription,
                standPhoto,
                status,
                city,
                longitude,
                latitude
            FROM stands
            LEFT JOIN bikes ON bikes.currentStand=stands.standId
            WHERE stands.status IN (" . implode(', ', $statusPlaceholders) . ")
            AND city IN (" . implode(', ', $cityPlaceholders) . ")
            GROUP BY standName
            ORDER BY standName",
            array_merge($statusParams, $cityParams)
        )->fetchAllAssoc();

        return $result;
    }

    /**
     * @return array<string, string>|null Map of bind-name (no colon) → configured city,
     *                                    or null if no cities are configured.
     */
    private function buildCityParams(): ?array
    {
        $names = array_keys($this->cityRepository->findAvailableCities());
        if (empty($names)) {
            return null;
        }

        $params = [];
        foreach ($names as $i => $name) {
            $params['city' . $i] = $name;
        }

        return $params;
    }

    public function findItem(int $standId): ?array
    {
        $stand = $this->db->query(
            "SELECT
                standId,
                standName,
                standDescription,
                standPhoto,
                status,
                placeName,
                city,
                longitude,
                latitude
            FROM stands
            WHERE standId = :standId",
            [
                'standId' => $standId,
            ]
        )->fetchAssoc();

        return $stand;
    }

    public function findItemByName(string $standName): ?array
    {
        $stand = $this->db->query(
            "SELECT
                standId,
                standName,
                standDescription,
                standPhoto,
                status,
                placeName,
                city,
                longitude,
                latitude
            FROM stands
            WHERE standName = :standName LIMIT 1",
            [
                'standName' => $standName,
            ]
        )->fetchAssoc();

        return $stand;
    }

    public function findFreeStands(): array
    {
        $result = $this->db->query(
            "SELECT
                count(bikes.bikeNum) as bikeCount,
                standName
            FROM stands
            LEFT JOIN bikes ON bikes.currentStand = stands.standId
            WHERE stands.status = :statusActive
            GROUP BY standName
            HAVING bikeCount = 0
            ORDER BY 2",
            ['statusActive' => StandStatus::ACTIVE->value]
        )->fetchAllAssoc();

        return $result;
    }

    public function findLastReturnedBikeOnStand(int $standId): ?int
    {
        $bikesOnStand = $this->db->query(
            "SELECT bikeNum FROM stands
            LEFT JOIN bikes ON bikes.currentStand=stands.standId
            WHERE standId=:standId",
            ['standId' => $standId]
        )->fetchAllAssoc();

        if (count($bikesOnStand)) {
            $bikeQueryParams = [];
            foreach ($bikesOnStand as $num => $bike) {
                $bikeQueryParams[':bikeNum' . $num] = $bike['bikeNum'];
            }

            $result = $this->db->query(
                "SELECT bikeNum FROM history
                WHERE action IN (:returnAction, :forceReturnAction)
                    AND parameter=:standId
                    AND bikeNum IN (" . implode(',', array_keys($bikeQueryParams)) . ")
                ORDER BY `time` DESC, id DESC
                LIMIT 1",
                array_merge(
                    [
                        'standId' => $standId,
                        'returnAction' => Action::RETURN->value,
                        'forceReturnAction' => Action::FORCE_RETURN->value,
                    ],
                    $bikeQueryParams,
                )
            )->fetchAssoc();

            return $result['bikeNum'] ?? null;
        }

        return null;
    }

    public function findBikesOnStand(int $standId): array
    {
        $result = $this->db->query(
            "SELECT bikeNum FROM bikes
            WHERE currentStand=:standId
            ORDER BY bikeNum",
            ['standId' => $standId]
        )->fetchAllAssoc();

        return $result;
    }

    public function updateStatus(int $standId, StandStatus $status): void
    {
        $this->db->query(
            "UPDATE stands SET status = :status WHERE standId = :standId",
            [
                'status' => $status->value,
                'standId' => $standId,
            ]
        );
    }
}
