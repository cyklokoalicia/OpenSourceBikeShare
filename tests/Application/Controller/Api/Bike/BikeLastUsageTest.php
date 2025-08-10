<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Enum\Action;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class BikeLastUsageTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 9;
    private const STAND_NAME = 'STAND1';

    public function testTrip(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($user);

        $rentSystemFactory = $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem('web');
        $rentSystemFactory->returnBike($user->getUserId(), self::BIKE_NUMBER, self::STAND_NAME, '', true);
        $rentSystemFactory->rentBike($user->getUserId(), self::BIKE_NUMBER);
        $rentSystemFactory->returnBike($user->getUserId(), self::BIKE_NUMBER, self::STAND_NAME);
        $rentSystemFactory->rentBike($user->getUserId(), self::BIKE_NUMBER);
        $rentSystemFactory->revertBike($user->getUserId(), self::BIKE_NUMBER);

        $this->client->request(Request::METHOD_GET, '/api/bike/' . self::BIKE_NUMBER . '/lastUsage');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response);
        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('notes', $responseData);
        $this->assertArrayHasKey('history', $responseData);
        $responseData['history'] = array_slice($responseData['history'], 0, 7);

        // Check that the history contains the expected fields
        foreach ($responseData['history'] as $history) {
            $this->assertArrayHasKey('time', $history);
            $this->assertArrayHasKey('action', $history);
            $this->assertArrayHasKey('userName', $history);
            $this->assertSame($user->getUserName(), $history['userName']);
        }

        // Check the history actions, the last action will be on the top
        $this->assertCount(7, $responseData['history']);
        $this->assertSame(Action::RETURN->value, $responseData['history'][0]['action']);
        $this->assertSame(Action::RENT->value, $responseData['history'][1]['action']);
        $this->assertSame(Action::REVERT->value, $responseData['history'][2]['action']);
        $this->assertSame(Action::RENT->value, $responseData['history'][3]['action']);
        $this->assertSame(Action::RETURN->value, $responseData['history'][4]['action']);
        $this->assertSame(Action::RENT->value, $responseData['history'][5]['action']);
        $this->assertSame(Action::FORCE_RETURN->value, $responseData['history'][6]['action']);

        $this->assertSame(
            $responseData['history'][2]['parameter'], //REVERT
            $responseData['history'][5]['parameter'], //RENT
            'Incorrect code after revert'
        );
    }
}
