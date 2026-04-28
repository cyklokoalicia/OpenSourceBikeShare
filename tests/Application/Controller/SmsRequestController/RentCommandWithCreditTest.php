<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Enum\CreditChangeType;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Translation\TranslatableResult;
use Symfony\Component\HttpFoundation\Request;

class RentCommandWithCreditTest extends BikeSharingWebTestCase
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
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);
        $userCredit = $creditSystem->getUserCredit($user['userId']);
        if ($userCredit > 0) {
            $creditSystem->decreaseCredit($user['userId'], $userCredit, CreditChangeType::BALANCE_ADJUSTMENT);
        }
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
    public function testRentCommand(
        bool $isCreditSystemEnabled,
        float $userCredit,
        bool $isSuccessRent,
        string $expectedCode
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

        // Drop SMS noise from setUp's FORCERETURN so we only assert on RENT's response.
        $this->client->getContainer()->get(DebugSmsSender::class)->reset();

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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        $this->assertCount(1, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableResult::class, $sentMessage['message']);
        $this->assertSame($expectedCode, $sentMessage['message']->getCode(), 'Invalid response sms code');

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);

        if ($isSuccessRent) {
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
            $params = $sentMessage['message']->getParams();
            $this->assertSame(
                str_pad((string)$history['parameter'], 4, '0', STR_PAD_LEFT),
                (string)$params['newCode'],
                'Response sms newCode does not match history'
            );
        } else {
            $this->assertNotEquals($user['userId'], $bike['userId'], 'Bike should not be rented');
            $this->assertNotNull($bike['standName'], 'Bike should be on stand');
        }
    }

    public static function rentCommandDataProvider(): iterable
    {
        yield 'Credit system enabled but user have no credits' => [
            'isCreditSystemEnabled' => true,
            'userCredit' => 0,
            'isSuccessRent' => false,
            'expectedCode' => 'bike.rent.error.insufficient_credit',
        ];
        yield 'Credit system enabled and user have credits' => [
            'isCreditSystemEnabled' => true,
            'userCredit' => 100,
            'isSuccessRent' => true,
            'expectedCode' => 'bike.rent.success',
        ];
    }
}
