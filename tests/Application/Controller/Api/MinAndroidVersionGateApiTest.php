<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force-update gate (spec 0005): the web rejects Android clients below
 * ANDROID_MIN_SUPPORTED_VERSION with 426 Upgrade Required at the /api/v1 firewall.
 * Uses the public cities endpoint so the gate (which runs before auth) is exercised
 * without a login.
 */
class MinAndroidVersionGateApiTest extends BikeSharingWebTestCase
{
    private const PUBLIC_ENDPOINT = '/api/v1/auth/cities';

    public function testOldAndroidClientBelowFloorIsBlockedWith426(): void
    {
        $this->withFloor('1.5.0');

        $this->client->request(Request::METHOD_GET, self::PUBLIC_ENDPOINT, server: [
            'HTTP_USER_AGENT' => 'OpenSourceBikeShare-Android/1.0.0 (1)',
        ]);

        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_UPGRADE_REQUIRED, $response->getStatusCode());
        $this->assertStringContainsString(
            'application/problem+json',
            (string)$response->headers->get('Content-Type'),
        );

        $problem = $this->decodeJsonResponse();
        $this->assertSame('upgrade_required', $problem['code']);
        $this->assertSame(Response::HTTP_UPGRADE_REQUIRED, $problem['status']);
    }

    public function testAndroidClientAtFloorPasses(): void
    {
        $this->withFloor('1.5.0');

        $this->client->request(Request::METHOD_GET, self::PUBLIC_ENDPOINT, server: [
            'HTTP_USER_AGENT' => 'OpenSourceBikeShare-Android/1.5.0 (10)',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testBrowserClientIsNeverBlocked(): void
    {
        $this->withFloor('1.5.0');

        $this->client->request(Request::METHOD_GET, self::PUBLIC_ENDPOINT, server: [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64)',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testGateDisabledWhenFloorUnset(): void
    {
        // Default env: ANDROID_MIN_SUPPORTED_VERSION is empty → no client is gated,
        // even a very old Android build. Uses the setUp client (no reboot needed).
        $this->client->request(Request::METHOD_GET, self::PUBLIC_ENDPOINT, server: [
            'HTTP_USER_AGENT' => 'OpenSourceBikeShare-Android/0.1.0 (1)',
        ]);

        $this->assertResponseIsSuccessful();
    }

    private function withFloor(string $version): void
    {
        $this->setEnvVar('ANDROID_MIN_SUPPORTED_VERSION', $version);
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }
}
