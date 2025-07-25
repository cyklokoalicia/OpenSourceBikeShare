<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class RentCommandWithCreditTest extends BikeSharingWebTestCase
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
            $creditSystem->useCredit($user['userId'], $userCredit);
        }
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
    public function testRentCommand(
        bool $isCreditSystemEnabled,
        float $userCredit,
        bool $isSuccessRent,
        string $expectedMessagePattern
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

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertMatchesRegularExpression(
            $expectedMessagePattern,
            $sentMessage['text'],
            'Invalid response sms text'
        );

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
            $this->assertStringContainsString(
                'Change code immediately to ' . str_pad($history['parameter'], 4, '0', STR_PAD_LEFT),
                $sentMessage['text'],
                'Response sms does not contain lock code'
            );
        } else {
            $this->assertNotEquals($user['userId'], $bike['userId'], 'Bike should not be rented');
            $this->assertNotNull($bike['standName'], 'Bike should be on stand');
        }
    }

    public function rentCommandDataProvider(): iterable
    {
        yield 'Credit system enabled but user have no credits' => [
            'isCreditSystemEnabled' => true,
            'userCredit' => 0,
            'isSuccessRent' => false,
            'expectedMessagePattern' => '/You are below required credit \d?.*\. Please, recharge your credit\./',
        ];
        yield 'Credit system enabled and user have credits' => [
            'isCreditSystemEnabled' => true,
            'userCredit' => 100,
            'isSuccessRent' => true,
            'expectedMessagePattern' => '/Bike ' . self::BIKE_NUMBER . ': Open with code \d{4}\.' .
                'Change code immediately to \d{4}' .
                '\(open, rotate metal part, set new code, rotate metal part back\)\./',
        ];
    }
}
