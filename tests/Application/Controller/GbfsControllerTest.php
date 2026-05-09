<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

class GbfsControllerTest extends BikeSharingWebTestCase
{
    public function testManifestListsAllFeedsPerLocale(): void
    {
        $this->client->request(Request::METHOD_GET, '/gbfs.json');
        $this->assertResponseIsSuccessful();

        $payload = $this->decodeJsonResponse();
        $this->assertSame('2.3', $payload['version']);
        $this->assertArrayHasKey('en', $payload['data']);

        $feedNames = array_column($payload['data']['en']['feeds'], 'name');
        $this->assertSame(
            ['system_information', 'station_information', 'station_status', 'vehicle_types'],
            $feedNames,
        );

        foreach ($payload['data']['en']['feeds'] as $feed) {
            $this->assertStringStartsWith('http://', $feed['url']);
            $this->assertStringEndsWith('.json', $feed['url']);
        }
    }

    public function testSystemInformationCarriesRequiredFields(): void
    {
        $this->client->request(Request::METHOD_GET, '/gbfs/en/system_information.json');
        $this->assertResponseIsSuccessful();

        $data = $this->decodeJsonResponse()['data'];
        $this->assertSame('test_bikeshare', $data['system_id']);
        $this->assertSame('en', $data['language']);
        $this->assertSame('UTC', $data['timezone']);
        $this->assertNotEmpty($data['name']);
    }

    public function testStationInformationExposesPublicStandsOnly(): void
    {
        $this->client->request(Request::METHOD_GET, '/gbfs/en/station_information.json');
        $this->assertResponseIsSuccessful();

        $stations = $this->decodeJsonResponse()['data']['stations'];
        $this->assertNotEmpty($stations);

        foreach ($stations as $station) {
            $this->assertArrayHasKey('station_id', $station);
            $this->assertArrayHasKey('name', $station);
            $this->assertIsFloat($station['lat']);
            $this->assertIsFloat($station['lon']);
        }

        $names = array_column($stations, 'name');
        $this->assertContains(
            'SERVICE_STAND',
            $names,
            'Maintenance stand must be advertised so clients can show it as out-of-service',
        );
        $this->assertNotContains('HIDDEN_STAND', $names);
        $this->assertNotContains('INACTIVE_STAND', $names);
        $this->assertNotContains('VIRTUAL_STAND', $names);
    }

    public function testStationStatusReportsBikeCounts(): void
    {
        $this->client->request(Request::METHOD_GET, '/gbfs/en/station_status.json');
        $this->assertResponseIsSuccessful();

        $stations = $this->decodeJsonResponse()['data']['stations'];
        $this->assertNotEmpty($stations);

        foreach ($stations as $station) {
            $this->assertIsString($station['station_id']);
            $this->assertIsInt($station['num_bikes_available']);
            $this->assertGreaterThanOrEqual(0, $station['num_bikes_available']);
            $this->assertNull($station['num_docks_available']);
            $this->assertTrue($station['is_installed']);
            $this->assertIsBool($station['is_renting']);
            $this->assertTrue($station['is_returning'], 'Every published stand accepts returns');
            $this->assertIsInt($station['last_reported']);
        }
    }

    public function testStationStatusMarksMaintenanceStandsNonRentable(): void
    {
        $this->client->request(Request::METHOD_GET, '/gbfs/en/station_information.json');
        $this->assertResponseIsSuccessful();
        $infoByStationId = [];
        foreach ($this->decodeJsonResponse()['data']['stations'] as $info) {
            $infoByStationId[$info['station_id']] = $info;
        }

        $this->client->request(Request::METHOD_GET, '/gbfs/en/station_status.json');
        $this->assertResponseIsSuccessful();
        $statuses = $this->decodeJsonResponse()['data']['stations'];

        $serviceStandStatus = null;
        $activeStandStatus = null;
        foreach ($statuses as $status) {
            $name = $infoByStationId[$status['station_id']]['name'] ?? null;
            if ($name === 'SERVICE_STAND') {
                $serviceStandStatus = $status;
            } elseif ($name === 'STAND1') {
                $activeStandStatus = $status;
            }
        }

        $this->assertNotNull($serviceStandStatus, 'SERVICE_STAND must appear in station_status');
        $this->assertTrue($serviceStandStatus['is_installed'], 'Maintenance stand stays physically installed');
        $this->assertFalse($serviceStandStatus['is_renting'], 'Maintenance stand must not be rentable');
        $this->assertTrue($serviceStandStatus['is_returning'], 'Maintenance stand still accepts returns');

        $this->assertNotNull($activeStandStatus, 'Sanity: active stand should appear too');
        $this->assertTrue($activeStandStatus['is_renting']);
        $this->assertTrue($activeStandStatus['is_returning']);
    }

    public function testVehicleTypesAdvertisesHumanBike(): void
    {
        $this->client->request(Request::METHOD_GET, '/gbfs/en/vehicle_types.json');
        $this->assertResponseIsSuccessful();

        $types = $this->decodeJsonResponse()['data']['vehicle_types'];
        $this->assertCount(1, $types);
        $this->assertSame('bike', $types[0]['vehicle_type_id']);
        $this->assertSame('bicycle', $types[0]['form_factor']);
        $this->assertSame('human', $types[0]['propulsion_type']);
    }

    public function testFeedsReturn404WhenDisabled(): void
    {
        $_ENV['GBFS_ENABLED'] = 'false';
        try {
            $this->client->restart();
            $this->client->request(Request::METHOD_GET, '/gbfs.json');
            $this->assertResponseStatusCodeSame(404);
        } finally {
            $_ENV['GBFS_ENABLED'] = 'true';
        }
    }

    public function testUnknownLocaleIs404(): void
    {
        $this->client->request(Request::METHOD_GET, '/gbfs/xyz/system_information.json');
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * `fr` passes the route's `[a-z]{2}` regex but is not in `kernel.enabled_locales`,
     * so the manifest doesn't advertise it and the per-locale endpoints must reject it.
     */
    #[DataProvider('localeScopedFeedProvider')]
    public function testNonEnabledLocaleReturns404(string $url): void
    {
        $this->client->request(Request::METHOD_GET, $url);
        $this->assertResponseStatusCodeSame(404);
    }

    public static function localeScopedFeedProvider(): array
    {
        return [
            'system_information' => ['/gbfs/fr/system_information.json'],
            'station_information' => ['/gbfs/fr/station_information.json'],
            'station_status' => ['/gbfs/fr/station_status.json'],
            'vehicle_types' => ['/gbfs/fr/vehicle_types.json'],
        ];
    }
}
