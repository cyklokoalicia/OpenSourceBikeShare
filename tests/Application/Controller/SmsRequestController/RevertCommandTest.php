<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class RevertCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const USER_PHONE_NUMBER = '421951111111';
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

        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem('sms')
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

        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeRevertEvent::class,
            function (BikeRevertEvent $event) use ($admin, $user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame($admin['userId'], $event->getRevertedByUserId());
                $this->assertSame($user['userId'], $event->getPreviousOwnerId());
            }
        );

        #rent bike
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'RENT ' . self::BIKE_NUMBER,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $pattern = '/Bike ' . self::BIKE_NUMBER . ': Open with code (?P<oldCode>\d{4})\.' .
            'Change code immediately to (?P<newCode>\d{4})' .
            '\(open, rotate metal part, set new code, rotate metal part back\)\./';
        $this->assertMatchesRegularExpression($pattern, $sentMessage['text'], 'Invalid response sms text');
        preg_match($pattern, $sentMessage['text'], $matches);
        $this->assertNotSame($matches['oldCode'], $matches['newCode'], 'Invalid lock code');

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'REVERT ' . self::BIKE_NUMBER,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ],
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(2, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessages = $smsConnector->getSentMessages();

        foreach ($sentMessages as $sentMessage) {
            if ($sentMessage['number'] === self::USER_PHONE_NUMBER) {
                $this->assertSame(
                    'Bike ' . self::BIKE_NUMBER . ' has been returned. You can now rent a new bicycle.',
                    $sentMessage['text'],
                    'Invalid response sms text for user'
                );
            } else {
                $this->assertSame(
                    'Bike ' . self::BIKE_NUMBER . ' reverted to ' . self::STAND_NAME .
                        ' with code ' . $matches['newCode'] . '.',
                    $sentMessage['text'],
                    'Invalid response sms text for admin'
                );
            }
        }

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $stand = $this->client->getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);

        $this->assertNull($bike['userId'], 'Bike is rented by user');
        $this->assertSame($stand['standName'], $bike['standName'], 'Bike is on invalid stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 3',
            [
                'userId' => $admin['userId'],
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAllAssoc();

        $this->assertSame('RETURN', $history[0]['action'], 'Invalid history action');
        $this->assertEquals(
            $stand['standId'],
            $history[0]['parameter'],
            'Missed standId'
        );

        $this->assertSame('RENT', $history[1]['action'], 'Invalid history action');
        $this->assertEquals(
            $matches['newCode'],
            $history[1]['parameter'],
            'Missed lock code'
        );

        $this->assertSame('REVERT', $history[2]['action'], 'Invalid history action');
        $this->assertEquals(
            $stand['standId'] . '|' . $matches['newCode'],
            $history[2]['parameter'],
            'Missed standId and lock code'
        );

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['stub'] === 'closure(BikeRevertEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }
    }
}
