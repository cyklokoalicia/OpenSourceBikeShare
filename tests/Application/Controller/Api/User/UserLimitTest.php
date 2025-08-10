<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\User;

use BikeShare\App\Security\UserProvider;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class UserLimitTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    public function testUserLimit(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/user/limit');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response);
        $data = json_decode($response, true);
        $this->assertArrayHasKey('limit', $data);
        $this->assertArrayHasKey('rented', $data);
        $this->assertArrayHasKey('userCredit', $data);
    }
}
