<?php

/**
 * Application tests for the ROLE_NEWBIE access gate (spec 0001).
 * With the SMS system enabled (SMS_CONNECTOR=debug in test), an unconfirmed user
 * is a newbie: blocked from action endpoints with a phone_unconfirmed 403, but
 * allowed to reach the phone-confirmation endpoints.
 */

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class NewbieAccessTest extends BikeSharingWebTestCase
{
    // userForApiPhoneConfirmTest — isNumberConfirmed: 0, password "password"
    private const NEWBIE_NUMBER = '421951666666';
    // userForLocaleChangeTest — confirmed (default), password "password"
    private const CONFIRMED_NUMBER = '421951555555';
    private const PASSWORD = 'password';

    public function testNewbieIsBlockedFromActionEndpointsWithPhoneUnconfirmedCode(): void
    {
        $token = $this->obtainToken(self::NEWBIE_NUMBER, self::PASSWORD);
        self::assertFalse($token['phoneConfirmed'], 'unconfirmed user should report phoneConfirmed=false');

        foreach (['/api/v1/me/bikes', '/api/v1/stands/markers'] as $path) {
            $this->client->request(
                Request::METHOD_GET,
                $path,
                [],
                [],
                ['HTTP_AUTHORIZATION' => 'Bearer ' . $token['accessToken']]
            );
            $this->assertResponseStatusCodeSame(403, "expected 403 for newbie on $path");
            $payload = $this->decodeJsonResponse();
            self::assertSame('phone_unconfirmed', $payload['code'] ?? null, "code for $path");
        }
    }

    public function testNewbieCanReachPhoneConfirmRequest(): void
    {
        $token = $this->obtainToken(self::NEWBIE_NUMBER, self::PASSWORD);

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/request',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token['accessToken']]
        );

        $this->assertResponseIsSuccessful();
        self::assertArrayHasKey('checkCode', $this->decodeApiResponseData());
    }

    public function testConfirmedUserReachesActionEndpoints(): void
    {
        $token = $this->obtainToken(self::CONFIRMED_NUMBER, self::PASSWORD);
        self::assertTrue($token['phoneConfirmed'], 'confirmed user should report phoneConfirmed=true');

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/me/bikes',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token['accessToken']]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testGenericForbiddenIsNotMarkedPhoneUnconfirmed(): void
    {
        // A confirmed non-admin hitting an admin endpoint gets a generic 403,
        // NOT phone_unconfirmed — proving the newbie code is discriminating.
        $token = $this->obtainToken(self::CONFIRMED_NUMBER, self::PASSWORD);

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/admin/users',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token['accessToken']]
        );

        $this->assertResponseStatusCodeSame(403);
        $payload = $this->decodeJsonResponse();
        self::assertArrayNotHasKey('code', $payload);
    }

    private function obtainToken(string $number, string $password): array
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/token',
            ['number' => $number, 'password' => $password]
        );
        $this->assertResponseIsSuccessful();

        return $this->decodeApiResponseData();
    }
}
