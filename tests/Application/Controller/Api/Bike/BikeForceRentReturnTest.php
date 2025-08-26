<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class BikeForceRentReturnTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 6;
    private const STAND_NAME = 'STAND1';

    private $watchesTooMany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watchesTooMany = $_ENV['WATCHES_NUMBER_TOO_MANY'];
    }

    protected function tearDown(): void
    {
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = $this->watchesTooMany;
        parent::tearDown();
    }

    public function testForce(): void
    {
        //We should not notify admin about too many rents in this testsuite
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 9999;

        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        //ForceRent
        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeRentEvent::class,
            function (BikeRentEvent $event) use ($admin) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(true, $event->isForce());
                $this->assertSame($admin->getUserId(), $event->getUserId());
            }
        );

        $this->client->request(Request::METHOD_PUT, '/api/bike/' . self::BIKE_NUMBER . '/forceRent');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertArrayHasKey('code', $response, 'Response does not contain code');
        $this->assertArrayHasKey('params', $response, 'Response does not contain params');
        $this->assertSame(0, $response['error'], 'Response with error: ' . json_encode($response));
        $this->assertArrayHasKey('bikeNumber', $response['params'], 'Response params does not contain bikeNumber');
        $this->assertArrayHasKey('currentCode', $response['params'], 'Response params does not contain currentCode');
        $this->assertArrayHasKey('newCode', $response['params'], 'Response params does not contain newCode');
        $this->assertArrayHasKey('note', $response['params'], 'Response params does not contain note');
        $this->assertSame($response['code'], 'bike.rent.success', 'Invalid response code');
        $this->assertSame($response['params']['bikeNumber'], self::BIKE_NUMBER, 'Invalid bike number');
        $this->assertNotSame(
            $response['params']['currentCode'],
            $response['params']['newCode'],
            'Codes should be different'
        );

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);

        $this->assertEquals($admin->getUserId(), $bike['userId'], 'Bike rented by another user');
        $this->assertNull($bike['standName'], 'Bike is still on stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $admin->getUserId(),
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame($history['action'], 'FORCERENT', 'Invalid history action');
        $this->assertNotEmpty($history['parameter'], 'Missed lock code');
        $this->assertSame($history['parameter'], $response['params']['newCode'], 'Invalid lock code');

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['pretty'] === 'BikeShare\EventListener\TooManyBikeRentEventListener::__invoke') {
                $this->fail('TooManyBikeRentEventListener was not called');
            }
        }

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/bike/' . self::BIKE_NUMBER . '/forceRent',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ': Open with code \d{4}\.\s*Change code immediately to \d{4}\s*' .
            '\(open, rotate metal part, set new code, rotate metal part back\)\./',
            $sent['text'],
            'Send message is not logged'
        );
        $this->assertStringContainsString(
            'Change code immediately to ' . str_pad($history['parameter'], 4, '0', STR_PAD_LEFT),
            $sent['text'],
            'Log record does not contain lock code'
        );

        //ForceReturn
        $this->client->request(
            Request::METHOD_PUT,
            '/api/bike/' . self::BIKE_NUMBER . '/forceReturn/' . self::STAND_NAME,
            [
                'note' => 'Bike returned from api test',
            ]
        );
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertArrayHasKey('code', $response, 'Response does not contain code');
        $this->assertArrayHasKey('params', $response, 'Response does not contain params');
        $this->assertSame(0, $response['error'], 'Response with error: ' . $response['message']);
        $this->assertSame($response['code'], 'bike.return.success', 'Invalid response code');
        $this->assertArrayHasKey('bikeNumber', $response['params'], 'Response params does not contain bikeNumber');
        $this->assertArrayHasKey('standName', $response['params'], 'Response params does not contain standName');
        $this->assertArrayHasKey('currentCode', $response['params'], 'Response params does not contain currentCode');
        $this->assertArrayHasKey('note', $response['params'], 'Response params does not contain note');
        $this->assertSame($response['params']['bikeNumber'], self::BIKE_NUMBER, 'Invalid bike number');

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $stand = $this->client->getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);

        $this->assertNull($bike['userId'], 'Bike is rented by user');
        $this->assertSame($stand['standName'], $bike['standName'], 'Bike is on invalid stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $admin->getUserId(),
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame('FORCERETURN', $history['action'], 'Invalid history action');
        $this->assertEquals($stand['standId'], $history['parameter'], 'Missed standId');

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['stub'] === 'closure(BikeReturnEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/bike/' . self::BIKE_NUMBER . '/forceReturn/' . self::STAND_NAME,
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ' returned to stand ' . self::STAND_NAME . '\.\s*Lock with code \d{4}\.\s*' .
            'Please, rotate the lockpad to 0000 when leaving\.\s*Wipe the bike clean if it is dirty, please\./',
            $sent['text'],
            'Send message is not logged'
        );
    }
}
