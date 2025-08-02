<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class WhereCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 7;
    private const STAND_NAME = 'STAND1';

    protected function tearDown(): void
    {
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
        parent::tearDown();
    }

    /**
     * @dataProvider whereCommandDataProvider
     */
    public function testWhereCommand(
        string $startAction,
        string $expectedMessagePattern
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

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertMatchesRegularExpression(
            $expectedMessagePattern,
            $sentMessage['text'],
            'Invalid response sms text'
        );
    }

    public function whereCommandDataProvider(): iterable
    {
        yield 'not rented bike' => [
            'startAction' => 'FORCERETURN ' . self::BIKE_NUMBER . ' ' . self::STAND_NAME,
            'expectedMessagePattern' => '/Bike ' . self::BIKE_NUMBER . ' is at stand ' . self::STAND_NAME . '/',
        ];
        yield 'rented bike' => [
            'startAction' => 'FORCERENT ' . self::BIKE_NUMBER,
            'expectedMessagePattern' => '/Bike ' . self::BIKE_NUMBER . ' is rented by .* \(\+\d*\)\./',
        ];
    }
}
