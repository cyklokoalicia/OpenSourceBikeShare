<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\User;

use BikeShare\App\Security\UserProvider;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class UserTripsTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    public function testTripsReturnsArray(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/v1/me/trips');
        $this->assertResponseIsSuccessful();
        $data = $this->decodeApiResponseData();
        $this->assertIsArray($data, 'Response data must be an array');
    }

    public function testTripsItemsHaveExpectedKeys(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/v1/me/trips');
        $this->assertResponseIsSuccessful();
        $data = $this->decodeApiResponseData();
        $this->assertIsArray($data);
        if (count($data) > 0) {
            $first = $data[0];
            $this->assertArrayHasKey('rentTime', $first);
            $this->assertArrayHasKey('bikeNumber', $first);
            $this->assertArrayHasKey('returnTime', $first);
            $this->assertArrayHasKey('standName', $first);
            $this->assertArrayHasKey('fromStandName', $first);
        }
    }

    public function testTripsReturnsAtMostTen(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/v1/me/trips');
        $this->assertResponseIsSuccessful();
        $data = $this->decodeApiResponseData();
        $this->assertLessThanOrEqual(10, count($data), 'API must return at most 10 trips');
    }

    public function testTripsRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/v1/me/trips');
        $this->assertResponseStatusCodeSame(401);
    }
}
