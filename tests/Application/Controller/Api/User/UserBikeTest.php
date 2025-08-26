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
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
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

    public function testUserBike(): void
    {
        //We should not notify admin about too many rents in this testsuite
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 9999;

        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        #rent bike
        $this->client->request(Request::METHOD_PUT, '/api/bike/' . self::BIKE_NUMBER . '/rent');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertArrayHasKey('code', $response, 'Response does not contain code');
        $this->assertArrayHasKey('params', $response, 'Response does not contain params');
        $this->assertSame(0, $response['error'], 'Response with error: ' . json_encode($response));
        $this->assertSame($response['code'], 'bike.rent.success', 'Invalid response code');
        $this->assertArrayHasKey('bikeNumber', $response['params'], 'Response params does not contain bikeNumber');
        $this->assertArrayHasKey('currentCode', $response['params'], 'Response params does not contain currentCode');
        $this->assertArrayHasKey('newCode', $response['params'], 'Response params does not contain newCode');
        $this->assertArrayHasKey('note', $response['params'], 'Response params does not contain note');
        $this->assertSame($response['params']['bikeNumber'], self::BIKE_NUMBER, 'Invalid bike number');
        $this->assertNotSame($response['params']['currentCode'], $response['params']['newCode'], 'Invalid lock code');

        $oldCode = $response['params']['currentCode'];
        $newCode = $response['params']['newCode'];

        #get user bikes info
        $this->client->request(Request::METHOD_GET, '/api/user/bike');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $response, 'Invalid number of rented bikes');
        $this->assertEquals(self::BIKE_NUMBER, $response[0]['bikeNum'], 'Invalid bike number');
        $this->assertSame($newCode, $response[0]['currentCode'], 'Invalid bike code');
        $this->assertSame($oldCode, $response[0]['oldCode'], 'Invalid old bike code');
        $this->assertArrayHasKey('rentedSeconds', $response[0], 'Rented seconds not found in response');
    }
}
