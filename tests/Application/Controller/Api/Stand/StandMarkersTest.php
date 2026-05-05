<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\App\Security\UserProvider;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class StandMarkersTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';

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
        $standNames = [];
        foreach ($response as $marker) {
            $this->assertArrayHasKey('standId', $marker, 'Marker does not contain standId');
            $this->assertArrayHasKey('bikeCount', $marker, 'Marker does not contain bikeCount');
            $this->assertArrayHasKey('standName', $marker, 'Marker does not contain standName');
            $this->assertArrayHasKey('standDescription', $marker, 'Marker does not contain standDescription');
            $this->assertArrayHasKey('standPhoto', $marker, 'Marker does not contain standPhoto');
            $this->assertArrayHasKey('longitude', $marker, 'Marker does not contain longitude');
            $this->assertArrayHasKey('latitude', $marker, 'Marker does not contain latitude');
            $this->assertArrayHasKey('status', $marker, 'Marker does not contain status');
            $this->assertContains(
                $marker['status'],
                ['active', 'technical'],
                'Regular user should not see hidden, inactive or virtual stands'
            );
            $standNames[] = $marker['standName'];
        }
        $this->assertNotContains('HIDDEN_STAND', $standNames);
        $this->assertNotContains('INACTIVE_STAND', $standNames);
        $this->assertNotContains('VIRTUAL_STAND', $standNames);
    }

    public function testMarkersByAdminIncludeHiddenButNotInactive(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_GET, '/api/v1/stands/markers');
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();

        $byName = [];
        foreach ($response as $marker) {
            $this->assertArrayHasKey('status', $marker);
            $byName[$marker['standName']] = $marker;
        }

        $this->assertArrayHasKey('HIDDEN_STAND', $byName, 'Admin should see hidden stand');
        $this->assertSame('hidden', $byName['HIDDEN_STAND']['status']);
        $this->assertArrayNotHasKey('INACTIVE_STAND', $byName, 'Admin should not see inactive stand');
        $this->assertArrayNotHasKey('VIRTUAL_STAND', $byName, 'Admin map should not include virtual stands either');
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
            $this->assertArrayHasKey('status', $marker, 'Marker does not contain status');
        }
    }

    public function testLegacyAndroidClientReceivesServiceTagBackport(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/stands/markers',
            server: ['HTTP_USER_AGENT' => 'okhttp/4.12.0']
        );
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertNotEmpty($response);
        foreach ($response as $marker) {
            $this->assertArrayHasKey('serviceTag', $marker, 'Legacy client should receive serviceTag');
            $this->assertArrayHasKey('status', $marker);
            if ($marker['status'] === 'technical' || $marker['status'] === 'hidden') {
                $this->assertSame(1, $marker['serviceTag']);
            } else {
                $this->assertSame(0, $marker['serviceTag']);
            }
        }
    }

    public function testNewAndroidClientDoesNotReceiveServiceTag(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/stands/markers',
            server: ['HTTP_USER_AGENT' => 'OpenSourceBikeShare-Android/1.1.2 (1)']
        );
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertNotEmpty($response);
        foreach ($response as $marker) {
            $this->assertArrayNotHasKey('serviceTag', $marker, 'New client should not receive legacy serviceTag');
            $this->assertArrayHasKey('status', $marker);
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
