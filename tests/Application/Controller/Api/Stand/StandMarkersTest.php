<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\App\Security\UserProvider;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class StandMarkersTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    protected function setUp(): void
    {
        $_ENV['SERVICE_API_TOKENS'] = '{"test-token": "testService"}';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_ENV['SERVICE_API_TOKENS'] = "{}";
        parent::tearDown();
    }

    public function testMarkersByUser(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/v1/stands/markers');
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        foreach ($response as $marker) {
            $this->assertArrayHasKey('standId', $marker, 'Marker does not contain standId');
            $this->assertArrayHasKey('bikeCount', $marker, 'Marker does not contain bikeCount');
            $this->assertArrayHasKey('standName', $marker, 'Marker does not contain standName');
            $this->assertArrayHasKey('standDescription', $marker, 'Marker does not contain standDescription');
            $this->assertArrayHasKey('standPhoto', $marker, 'Marker does not contain standPhoto');
            $this->assertArrayHasKey('longitude', $marker, 'Marker does not contain longitude');
            $this->assertArrayHasKey('latitude', $marker, 'Marker does not contain latitude');
        }
    }

    public function testMarkersByToken(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/stands/markers',
            server: ['HTTP_AUTHORIZATION' => 'Bearer test-token']
        );
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        foreach ($response as $marker) {
            $this->assertArrayHasKey('standId', $marker, 'Marker does not contain standId');
            $this->assertArrayHasKey('bikeCount', $marker, 'Marker does not contain bikeCount');
            $this->assertArrayHasKey('standName', $marker, 'Marker does not contain standName');
            $this->assertArrayHasKey('standDescription', $marker, 'Marker does not contain standDescription');
            $this->assertArrayHasKey('standPhoto', $marker, 'Marker does not contain standPhoto');
            $this->assertArrayHasKey('longitude', $marker, 'Marker does not contain longitude');
            $this->assertArrayHasKey('latitude', $marker, 'Marker does not contain latitude');
        }
    }

    public function testServiceTokenCannotAccessStandList(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/admin/stands',
            server: ['HTTP_AUTHORIZATION' => 'Bearer test-token']
        );

        $this->assertResponseStatusCodeSame(403);
        $payload = $this->decodeJsonResponse();
        $this->assertSame('Access denied', $payload['detail'] ?? null);
    }
}
