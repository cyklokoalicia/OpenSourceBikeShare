<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class BikeTripTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 7;
    private const STAND_NAME = 'STAND1';

    public function testTrip(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($user);

        $rentSystemFactory = $this->client->getContainer()
            ->get(RentSystemFactory::class)
            ->getRentSystem(RentSystemType::WEB);
        $rentSystemFactory->rentBike($user->getUserId(), self::BIKE_NUMBER, true);
        $rentSystemFactory->returnBike($user->getUserId(), self::BIKE_NUMBER, self::STAND_NAME);

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/bikes/' . self::BIKE_NUMBER . '/trip');
        $this->assertResponseIsSuccessful();
        $responseData = $this->decodeApiResponseData();
        foreach ($responseData as $trip) {
            $this->assertArrayHasKey('longitude', $trip);
            $this->assertArrayHasKey('latitude', $trip);
            $this->assertArrayHasKey('time', $trip);
        }

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/v1/admin/bikes/' . self::BIKE_NUMBER . '/trip',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');
        #We do not save the response for this api request, so there is no response check for this test
    }
}
