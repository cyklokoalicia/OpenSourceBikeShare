<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\User;

use BikeShare\App\Security\UserProvider;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class UserBikeTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 7;
    private const STAND_NAME = 'STAND1';

    private $watchesTooMany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watchesTooMany = $_ENV['WATCHES_NUMBER_TOO_MANY'];

        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );
    }

    protected function tearDown(): void
    {
        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );

        $_ENV['WATCHES_NUMBER_TOO_MANY'] = $this->watchesTooMany;
        parent::tearDown();
    }

    public function testChangeCity(): void
    {
        //We should not notify admin about too many rents in this testsuite
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 9999;

        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_PUT, '/api/bike/' . self::BIKE_NUMBER . '/rent');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertSame(0, $response['error'], 'Response with error: ' . $response['message']);
        $response = strip_tags($response['message']);
        $pattern = '/Bike ' . self::BIKE_NUMBER . ': Open with code (?P<oldCode>\d{4})\.' .
            'Change code immediately to (?P<newCode>\d{4})' .
            '\(open, rotate metal part, set new code, rotate metal part back\)\./';
        $this->assertMatchesRegularExpression($pattern, $response, 'Invalid response text');
        preg_match($pattern, $response, $matches);

        $this->client->request(Request::METHOD_GET, '/api/user/bike');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $response, 'Invalid number of rented bikes');
        $this->assertEquals(self::BIKE_NUMBER, $response[0]['bikeNum'], 'Invalid bike number');
        $this->assertSame($matches['newCode'], $response[0]['currentCode'], 'Invalid bike code');
        $this->assertSame($matches['oldCode'], $response[0]['oldCode'], 'Invalid old bike code');
        $this->assertArrayHasKey('rentedSeconds', $response[0], 'Rented seconds not found in response');
    }
}
