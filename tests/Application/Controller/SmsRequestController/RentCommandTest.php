<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Translation\TranslatableResult;
use Symfony\Component\HttpFoundation\Request;

class RentCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 5;
    private const STAND_NAME = 'STAND5';

    private $watchesTooMany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watchesTooMany = $_ENV['WATCHES_NUMBER_TOO_MANY'];

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

        // Drop any setup-noise from the recording SMS sender so the test sees only what RENT produces.
        $this->client->getContainer()->get(DebugSmsSender::class)->reset();
    }

    protected function tearDown(): void
    {
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

        $_ENV['WATCHES_NUMBER_TOO_MANY'] = $this->watchesTooMany;
        parent::tearDown();
    }

    public function testRentCommand(): void
    {
        //We should not notify admin about too many rents in this testsuite
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 9999;

        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeRentEvent::class,
            function (BikeRentEvent $event) use ($user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(false, $event->isForce());
                $this->assertSame($user['userId'], $event->getUserId());
            }
        );

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'RENT ' . self::BIKE_NUMBER,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ],
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $sentMessages = $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages();
        $this->assertCount(1, $sentMessages, 'Invalid number of sent messages');
        $sentMessage = $sentMessages[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableResult::class, $sentMessage['message']);
        $this->assertSame('bike.rent.success', $sentMessage['message']->getCode());
        $params = $sentMessage['message']->getParams();
        $this->assertSame(self::BIKE_NUMBER, $params['bikeNumber']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $params['currentCode']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $params['newCode']);
        $this->assertNotSame($params['currentCode'], $params['newCode']);
        $this->assertSame('false', $params['hasNote']);

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);

        $this->assertEquals($user['userId'], $bike['userId'], 'Bike rented by another user');
        $this->assertNull($bike['standName'], 'Bike is still on stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $user['userId'],
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame($history['action'], 'RENT', 'Invalid history action');
        $this->assertNotEmpty($history['parameter'], 'Missed lock code');
        $this->assertSame(
            str_pad($history['parameter'], 4, '0', STR_PAD_LEFT),
            $params['newCode'],
            'History lock code does not match newCode in SMS params'
        );

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['pretty'] === 'BikeShare\EventListener\TooManyBikeRentEventListener::__invoke') {
                $this->fail('TooManyBikeRentEventListener was not called');
            }
        }
    }
}
