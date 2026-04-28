<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Rent\Enum\RentSystemType;
use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class WhereCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 7;
    private const STAND_NAME = 'STAND1';

    protected function tearDown(): void
    {
        #force return bike
        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );
        parent::tearDown();
    }

    #[DataProvider('whereCommandDataProvider')]
    public function testWhereCommand(
        string $startAction,
        string $expectedCode,
        bool $expectAdminAsCurrentUser
    ): void {
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => $startAction,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'WHERE ' . self::BIKE_NUMBER,
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
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame($expectedCode, $sentMessage['message']->getMessage());

        if ($expectAdminAsCurrentUser) {
            $admin = $this->client->getContainer()->get(UserRepository::class)
                ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
            $this->assertSame(
                [
                    'bikeNumber' => self::BIKE_NUMBER,
                    'userName' => $admin['userName'],
                    'phone' => self::ADMIN_PHONE_NUMBER,
                    'note' => '',
                ],
                $sentMessage['message']->getParameters()
            );
        } else {
            $this->assertSame(
                [
                    'bikeNumber' => self::BIKE_NUMBER,
                    'standName' => self::STAND_NAME,
                    'note' => '',
                ],
                $sentMessage['message']->getParameters()
            );
        }
    }

    public static function whereCommandDataProvider(): iterable
    {
        yield 'not rented bike' => [
            'startAction' => 'FORCERETURN ' . self::BIKE_NUMBER . ' ' . self::STAND_NAME,
            'expectedCode' => 'command.where.at_stand',
            'expectAdminAsCurrentUser' => false,
        ];
        yield 'rented bike' => [
            'startAction' => 'FORCERENT ' . self::BIKE_NUMBER,
            'expectedCode' => 'command.where.in_use',
            'expectAdminAsCurrentUser' => true,
        ];
    }
}
