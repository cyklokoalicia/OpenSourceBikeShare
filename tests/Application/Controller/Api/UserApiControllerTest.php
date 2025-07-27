<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class UserApiControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';

    private $cities;

    protected function setup(): void
    {
        $this->cities = $_ENV['CITIES'];
        parent::setup();
        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        $user = $userRepository->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $userRepository->updateUserCity(
            $user['userId'],
            'Default City'
        );
    }

    protected function tearDown(): void
    {
        $_ENV['CITIES'] = $this->cities;
        parent::tearDown();
    }

    public function testChangeCity(): void
    {
        $_ENV['CITIES'] = json_encode([
            "Default City" => [48.148154, 17.117232],
            "Bratislava" => [17.117232, 48.148154],
        ]);

        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);
        $originalCity = $user->getCity();
        $this->assertSame('Default City', $originalCity, 'User is not in default city');

        $this->client->request('PUT', '/api/user/changeCity', ['city' => 'Bratislava']);
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertJson($response->getContent());
        $response = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertSame(0, $response['error'], 'Response with error: ' . $response['message']);

        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->assertSame('Bratislava', $user->getCity(), 'User city was not changed');

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/user/changeCity',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame('City changed successfully', $sent['text'], 'Send message is not logged');
    }
}
