<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Enum\CreditChangeType;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Translation\TranslatableResult;
use Symfony\Component\HttpFoundation\Request;

class ReturnCommandWithCreditTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
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
            $creditSystem->decreaseCredit($user['userId'], $userCredit, CreditChangeType::BALANCE_ADJUSTMENT);
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

    #[DataProvider('rentCommandDataProvider')]
    public function testReturnCommand(
        float $userCredit,
        float $expectedCreditLeft,
        int $returnTimeMoveToFuture
    ): void {
        //We should not notify admin about too many rents in this testsuite
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 9999;

        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $this->client->getContainer()->get(CreditSystemInterface::class)
            ->increaseCredit(
                $user['userId'],
                $userCredit,
                CreditChangeType::CREDIT_ADD
            );

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

        // Drop SMS noise from prep so we only assert on RETURN's response.
        $this->client->getContainer()->get(DebugSmsSender::class)->reset();

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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        $this->assertCount(1, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableResult::class, $sentMessage['message']);
        $this->assertSame('bike.return.success', $sentMessage['message']->getCode());
        $params = $sentMessage['message']->getParams();
        $this->assertSame(self::BIKE_NUMBER, $params['bikeNumber']);
        $this->assertSame(self::STAND_NAME, $params['standName']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string)$params['currentCode']);

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

    public static function rentCommandDataProvider(): iterable
    {
        yield 'return without credit charge' => [
            'userCredit' => 100,
            'expectedCreditLeft' => 100,
            'returnTimeMoveToFuture' => 0,
        ];
    }
}
