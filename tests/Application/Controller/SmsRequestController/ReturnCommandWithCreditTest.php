<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ReturnCommandWithCreditTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 5;
    private const STAND_NAME = 'STAND5';

    protected const CONTAINER_REBOOT_DISABLED = true;

    private $watchesTooMany;
    private $creditSystemEnabled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watchesTooMany = $_ENV['WATCHES_NUMBER_TOO_MANY'];
        $this->creditSystemEnabled = $_ENV['CREDIT_SYSTEM_ENABLED'];
        $_ENV['CREDIT_SYSTEM_ENABLED'] = '1';
    }

    protected function tearDown(): void
    {
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);
        $userCredit = $creditSystem->getUserCredit($user['userId']);
        if ($userCredit > 0) {
            $creditSystem->useCredit($user['userId'], $userCredit);
        }
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'FORCERETURN ' . self::BIKE_NUMBER . ' ' . self::STAND_NAME,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );

        $_ENV['WATCHES_NUMBER_TOO_MANY'] = $this->watchesTooMany;
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $this->creditSystemEnabled;
        parent::tearDown();
    }

    /**
     * @dataProvider rentCommandDataProvider
     */
    public function testReturnCommand(
        float $userCredit,
        float $expectedCreditLeft,
        int $returnTimeMoveToFuture
    ): void {
        //We should not notify admin about too many rents in this testsuite
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 9999;

        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $this->client->getContainer()->get(CreditSystemInterface::class)->addCredit($user['userId'], $userCredit);

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeRentEvent::class,
            function (BikeRentEvent $event) use ($user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(false, $event->isForce());
                $this->assertSame($user['userId'], $event->getUserId());
            }
        );

        #force return bike by admin
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'FORCERETURN ' . self::BIKE_NUMBER . ' ' . self::STAND_NAME,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );

        #rent bike by user
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

        #return bike
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'RETURN ' . self::BIKE_NUMBER . ' ' . self::STAND_NAME,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ],
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ' returned to stand ' . self::STAND_NAME . ' : Lock with code \d{4}\.' .
            'Please, rotate the lockpad to 0000 when leaving\.Wipe the bike clean if it is dirty, please\./',
            $sentMessage['text'],
            'Invalid response sms text'
        );

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $stand = $this->client->getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);

        $this->assertNull($bike['userId'], 'Bike is rented by user');
        $this->assertSame($stand['standName'], $bike['standName'], 'Bike is on invalid stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $user['userId'],
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame('RETURN', $history['action'], 'Invalid history action');
        $this->assertEquals($stand['standId'], $history['parameter'], 'Missed standId');

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['stub'] === 'closure(BikeReturnEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }
        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);
        $this->assertSame($expectedCreditLeft, $creditSystem->getUserCredit($user['userId']), 'Invalid credit left');
    }

    public function rentCommandDataProvider(): iterable
    {
        yield 'return without credit charge' => [
            'userCredit' => 100,
            'expectedCreditLeft' => 100,
            'returnTimeMoveToFuture' => 0,
        ];
    }
}
