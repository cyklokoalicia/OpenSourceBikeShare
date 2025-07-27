<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class BikeRevertTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const USER_PHONE_NUMBER = '421111111111';
    private const BIKE_NUMBER = 7;
    private const STAND_NAME = 'STAND1';

    private $watchesTooMany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watchesTooMany = $_ENV['WATCHES_NUMBER_TOO_MANY'];

        #force return bike
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
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = $this->watchesTooMany;
        parent::tearDown();
    }

    public function testRevertCommand(): void
    {
        //We should not notify admin about too many rents in this testsuite
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 9999;

        $userProvider = $this->client->getContainer()->get(UserProvider::class);
        $user = $userProvider->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $admin = $userProvider->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);

        $this->client->loginUser($user);

        #rent bike
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
        $this->assertNotSame($matches['oldCode'], $matches['newCode'], 'Invalid lock code');


        #revert bike
        $this->client->loginUser($admin);
        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeRevertEvent::class,
            function (BikeRevertEvent $event) use ($admin, $user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame($admin->getUserId(), $event->getRevertedByUserId());
                $this->assertSame($user->getUserId(), $event->getPreviousOwnerId());
            }
        );

        $this->client->request(Request::METHOD_PUT, '/api/bike/' . self::BIKE_NUMBER . '/revert');

        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertSame(0, $response['error'], 'Response with error: ' . $response['message']);
        $response = strip_tags($response['message']);
        $this->assertSame(
            'Bike ' . self::BIKE_NUMBER . ' reverted to ' . self::STAND_NAME .
            ' with code ' . $matches['newCode'] . '.',
            $response,
            'Invalid response text for admin'
        );

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount(1, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsConnector->getSentMessages()[0];
        $this->assertSame(
            'Bike ' . self::BIKE_NUMBER . ' has been returned. You can now rent a new bicycle.',
            $sentMessage['text'],
            'Invalid response sms text for user'
        );

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

        $this->assertSame('REVERT', $history['action'], 'Invalid history action');
        $this->assertEquals(
            $stand['standId'] . '|' . $matches['newCode'],
            $history['parameter'],
            'Missed standId and lock code'
        );

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['stub'] === 'closure(BikeRevertEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/bike/' . self::BIKE_NUMBER . '/revert',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            'Bike ' . self::BIKE_NUMBER . ' reverted to ' . self::STAND_NAME .
            ' with code ' . $matches['newCode'] . '.',
            $sent['text'],
            'Send message is not logged'
        );
    }
}
