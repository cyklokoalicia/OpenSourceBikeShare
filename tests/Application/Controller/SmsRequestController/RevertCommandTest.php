<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Translation\TranslatableResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

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

        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        $this->assertCount(1, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableResult::class, $sentMessage['message']);
        $this->assertSame('bike.rent.success', $sentMessage['message']->getCode());
        $rentParams = $sentMessage['message']->getParams();
        $this->assertSame(self::BIKE_NUMBER, $rentParams['bikeNumber']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string)$rentParams['currentCode']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string)$rentParams['newCode']);
        $this->assertNotSame($rentParams['currentCode'], $rentParams['newCode'], 'Invalid lock code');

        $newCode = $rentParams['newCode'];

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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $this->assertCount(2, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessages = $smsSender->getSentMessages();

        foreach ($sentMessages as $sentMessage) {
            if ($sentMessage['number'] === self::USER_PHONE_NUMBER) {
                $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
                $this->assertSame(
                    'bike.revert.notification.previous_owner',
                    $sentMessage['message']->getMessage()
                );
                $this->assertSame(
                    ['bikeNumber' => self::BIKE_NUMBER],
                    $sentMessage['message']->getParameters()
                );
            } else {
                $this->assertInstanceOf(TranslatableResult::class, $sentMessage['message']);
                $this->assertSame('bike.revert.success', $sentMessage['message']->getCode());
                $this->assertSame(
                    [
                        'bikeNumber' => self::BIKE_NUMBER,
                        'standName' => self::STAND_NAME,
                        'code' => $newCode,
                    ],
                    $sentMessage['message']->getParams()
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
            $newCode,
            $history[1]['parameter'],
            'Missed lock code'
        );

        $this->assertSame('REVERT', $history[2]['action'], 'Invalid history action');
        $this->assertEquals(
            $stand['standId'] . '|' . $newCode,
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
