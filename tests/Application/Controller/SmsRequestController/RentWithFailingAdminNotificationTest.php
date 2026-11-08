<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Translation\TranslatableResult;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class RentWithFailingAdminNotificationTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 5;
    private const STAND_NAME = 'STAND5';

    protected function setUp(): void
    {
        // Any rent trips the too-many-rents alert.
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '-1000');
        parent::setUp();

        $container = $this->client->getContainer();
        $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
            ->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        // Fixtures have a single admin with a phone number; make a second one so a survivor can be asserted.
        $container->get(DbInterface::class)->query(
            'UPDATE users SET privileges = 3 WHERE userId = :userId',
            ['userId' => $admin['userId']]
        );

        $container->get(DebugSmsSender::class)->reset();
    }

    protected function tearDown(): void
    {
        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
            ->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        $this->client->getContainer()->get(DbInterface::class)->query(
            'UPDATE users SET privileges = 1 WHERE userId = :userId',
            ['userId' => $admin['userId']]
        );

        parent::tearDown();
    }

    public function testFailingTooManyRentsAdminSmsDoesNotBreakRent(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');

        $container = $this->client->getContainer();
        $user = $container->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $admins = array_values(array_filter(
            $container->get(UserRepository::class)->findAdmins(),
            static fn (array $a): bool => !empty($a['number']) && (int)$a['userId'] !== (int)$user['userId']
        ));
        $this->assertGreaterThanOrEqual(2, count($admins), 'Need a failing admin and a surviving one');
        $failing = $admins[0]['number'];
        $container->get(DebugSmsSender::class)->failFor($failing);

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

        $sms = $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages();

        $userReplies = array_values(array_filter(
            $sms,
            static fn (array $m): bool => $m['number'] === self::USER_PHONE_NUMBER
        ));
        $this->assertCount(1, $userReplies);
        $this->assertInstanceOf(TranslatableResult::class, $userReplies[0]['message']);
        $this->assertSame('bike.rent.success', $userReplies[0]['message']->getCode());

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $this->assertEquals($user['userId'], $bike['userId']);

        $alerted = array_column(array_filter(
            $sms,
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'admin.notification.too_many_rents'
        ), 'number');
        $expected = array_values(array_diff(array_column($admins, 'number'), [$failing]));
        $this->assertNotEmpty($expected);
        $this->assertEqualsCanonicalizing($expected, $alerted);
    }
}
