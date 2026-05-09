<?php

declare(strict_types=1);

namespace BikeShare\Gbfs;

use BikeShare\Enum\StandStatus;
use BikeShare\Repository\StandRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds GBFS v2.3 feed payloads. Spec: https://github.com/MobilityData/gbfs/blob/v2.3/gbfs.md
 */
class GbfsFeedBuilder
{
    private const SPEC_VERSION = '2.3';

    private const TTL_MANIFEST = 0;
    private const TTL_SYSTEM_INFORMATION = 1800;
    private const TTL_STATION_INFORMATION = 300;
    private const TTL_STATION_STATUS = 60;
    private const TTL_VEHICLE_TYPES = 0;

    private const VEHICLE_TYPE_BIKE = 'bike';

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly StandRepository $standRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $systemId,
        private readonly string $systemName,
        private readonly string $timezone,
        private readonly string $contactEmail,
        private readonly string $defaultLocale,
        /** @var list<string> */
        private readonly array $enabledLocales,
    ) {
    }

    public function buildManifest(): array
    {
        $feedNames = [
            'system_information',
            'station_information',
            'station_status',
            'vehicle_types',
        ];

        $perLocaleFeeds = [];
        foreach ($this->enabledLocales as $locale) {
            $perLocaleFeeds[$locale] = [
                'feeds' => array_map(
                    fn(string $name) => [
                        'name' => $name,
                        'url' => $this->urlGenerator->generate(
                            'gbfs_' . $name,
                            ['locale' => $locale],
                            UrlGeneratorInterface::ABSOLUTE_URL,
                        ),
                    ],
                    $feedNames,
                ),
            ];
        }

        return $this->envelope(self::TTL_MANIFEST, $perLocaleFeeds);
    }

    public function buildSystemInformation(string $locale): array
    {
        return $this->envelope(self::TTL_SYSTEM_INFORMATION, [
            'system_id' => $this->systemId,
            'language' => $locale,
            'name' => $this->systemName,
            'timezone' => $this->timezone,
            'email' => $this->contactEmail,
        ]);
    }

    public function buildStationInformation(): array
    {
        $stations = [];
        foreach ($this->fetchPublicStands() as $stand) {
            $stations[] = [
                'station_id' => (string)$stand['standId'],
                'name' => $stand['standName'],
                'lat' => (float)$stand['latitude'],
                'lon' => (float)$stand['longitude'],
            ];
        }

        return $this->envelope(self::TTL_STATION_INFORMATION, ['stations' => $stations]);
    }

    public function buildStationStatus(): array
    {
        $now = $this->clock->now()->getTimestamp();
        $stations = [];
        foreach ($this->fetchPublicStands() as $stand) {
            // is_returning stays true even for TECHNICAL stands — returnBike()
            // does not gate by stand status, so users can park there.
            $isRentable = StandStatus::from($stand['status'])->isRentablePublic();
            $stations[] = [
                'station_id' => (string)$stand['standId'],
                'num_bikes_available' => (int)$stand['bikeCount'],
                'num_docks_available' => null,
                'is_installed' => true,
                'is_renting' => $isRentable,
                'is_returning' => true,
                'last_reported' => $now,
            ];
        }

        return $this->envelope(self::TTL_STATION_STATUS, ['stations' => $stations]);
    }

    public function buildVehicleTypes(): array
    {
        return $this->envelope(self::TTL_VEHICLE_TYPES, [
            'vehicle_types' => [
                [
                    'vehicle_type_id' => self::VEHICLE_TYPE_BIKE,
                    'form_factor' => 'bicycle',
                    'propulsion_type' => 'human',
                    'name' => 'Bike',
                ],
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPublicStands(): array
    {
        // VIRTUAL is intentionally excluded — those stands are real rent endpoints
        // (festivals, reserve storage) but carry placeholder lat/lng=0,0 that would
        // render as Gulf-of-Guinea pins on aggregator maps.
        return $this->standRepository->findAllExtended(null, [StandStatus::ACTIVE, StandStatus::TECHNICAL]);
    }

    private function envelope(int $ttl, array $data): array
    {
        return [
            'last_updated' => $this->clock->now()->getTimestamp(),
            'ttl' => $ttl,
            'version' => self::SPEC_VERSION,
            'data' => $data,
        ];
    }
}
