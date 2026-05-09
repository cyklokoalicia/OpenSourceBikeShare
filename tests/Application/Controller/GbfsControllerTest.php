<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Test\Application\BikeSharingWebTestCase;
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
        $this->assertNotContains('SERVICE_STAND', $names);
        $this->assertNotContains('HIDDEN_STAND', $names);
        $this->assertNotContains('INACTIVE_STAND', $names);
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
            $this->assertTrue($station['is_renting']);
            $this->assertTrue($station['is_returning']);
            $this->assertIsInt($station['last_reported']);
        }
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
}
