<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class BikeSetCodeTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 7;

    public function testSetCode(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $db = $this->client->getContainer()->get(DbInterface::class);
        $bikeRow = $db->query(
            'SELECT currentCode FROM bikes WHERE bikeNum = :bikeNumber',
            ['bikeNumber' => self::BIKE_NUMBER]
        )->fetchAssoc();

        $currentCode = str_pad((string)$bikeRow['currentCode'], 4, '0', STR_PAD_LEFT);
        $newCode = '4321';
        if ($newCode === $currentCode) {
            $newCode = '1234';
        }

        $this->client->request(
            Request::METHOD_PUT,
            '/api/bike/' . self::BIKE_NUMBER . '/code',
            ['code' => $newCode],
        );

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertArrayHasKey('bikeNumber', $response, 'Response does not contain bikeNumber');
        $this->assertArrayHasKey('code', $response, 'Response does not contain code');
        $this->assertSame(0, $response['error'], 'Response with error: ' . $response['message']);
        $this->assertSame(self::BIKE_NUMBER, $response['bikeNumber'], 'Invalid bike number');
        $this->assertSame($newCode, $response['code'], 'Invalid lock code');

        $updatedBike = $db->query(
            'SELECT currentCode FROM bikes WHERE bikeNum = :bikeNumber',
            ['bikeNumber' => self::BIKE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            $newCode,
            str_pad((string)$updatedBike['currentCode'], 4, '0', STR_PAD_LEFT),
            'Bike code was not updated'
        );

        $history = $db->query(
            'SELECT action, parameter FROM history WHERE bikeNum = :bikeNumber ORDER BY time DESC, id DESC LIMIT 1',
            ['bikeNumber' => self::BIKE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(Action::CHANGE_CODE->value, $history['action'], 'Invalid history action');
        $this->assertSame(
            $newCode,
            str_pad((string)$history['parameter'], 4, '0', STR_PAD_LEFT),
            'History parameter does not contain new code'
        );

        $received = $db->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/bike/' . self::BIKE_NUMBER . '/code',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $db->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertStringContainsString('Bike ' . self::BIKE_NUMBER, $sent['text'], 'Send message is not logged');
        $this->assertStringContainsString($newCode, $sent['text'], 'Send message does not contain new code');
    }
}
