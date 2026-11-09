<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Mail\MailSenderInterface;
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

class ReturnWithNoteNotificationTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 6;
    private const STAND_NAME = 'STAND1';
    private const NOTE = 'Rear brake squeaks';

    protected function setUp(): void
    {
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        parent::setUp();

        $container = $this->client->getContainer();
        $userRepository = $container->get(UserRepository::class);
        $admin = $userRepository->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem = $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS);
        $rentSystem->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        $user = $userRepository->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $rent = $rentSystem->rentBike($user['userId'], self::BIKE_NUMBER);
        $this->assertFalse($rent->isError(), 'Setup rent failed: ' . $rent->getCode());

        $container->get(DebugSmsSender::class)->reset();
        $container->get(MailSenderInterface::class)->reset();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
            ->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        parent::tearDown();
    }

    public function testReturnWithNoteNotifiesAdmins(): void
    {
        $this->sendReturn(self::NOTE);

        $this->assertReturnSucceeded();
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $admins = $this->admins();
        $this->assertCount(2, $admins);

        $alerts = $this->noteAlertSms();
        $this->assertEqualsCanonicalizing(array_column($admins, 'number'), array_column($alerts, 'number'));
        foreach ($alerts as $alert) {
            $this->assertEquals(
                new TranslatableMessage('bike.note.admin.notification', [
                    'bikeNumber' => self::BIKE_NUMBER,
                    'bikeStatus' => new TranslatableMessage('bike.status.at_stand', ['standName' => self::STAND_NAME]),
                    'userName' => $user['userName'],
                    'phone' => $user['number'],
                    'userNote' => self::NOTE,
                ]),
                $alert['message']
            );
        }

        $mails = $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages();
        $this->assertEqualsCanonicalizing(array_column($admins, 'mail'), array_column($mails, 'recipient'));
        foreach ($mails as $mail) {
            $this->assertSame('Bike note added', $mail['subject']);
            $this->assertSame(
                sprintf(
                    'Note: b.%d (at %s) by %s/%s:%s',
                    self::BIKE_NUMBER,
                    self::STAND_NAME,
                    $user['userName'],
                    $user['number'],
                    self::NOTE
                ),
                $mail['message']
            );
        }
    }

    public function testReturnWithoutNoteSendsNoAdminNotification(): void
    {
        $this->sendReturn(null);

        $this->assertReturnSucceeded();
        $this->assertSame([], $this->noteAlertSms());
        $this->assertCount(1, $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages());
        $this->assertSame([], $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages());
    }

    public function testAdminSmsFailureDoesNotBreakReturn(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');
        $admins = $this->admins();
        $this->assertCount(2, $admins);
        $failing = $admins[0]['number'];
        $this->client->getContainer()->get(DebugSmsSender::class)->failFor($failing);

        $this->sendReturn(self::NOTE);

        $this->assertReturnSucceeded();
        $this->assertSame(
            [$admins[1]['number']],
            array_column($this->noteAlertSms(), 'number'),
            'The admin after the failing one must still get the note alert'
        );
    }

    private function sendReturn(?string $note): void
    {
        $message = 'RETURN ' . self::BIKE_NUMBER . ' ' . self::STAND_NAME . ($note !== null ? ' ' . $note : '');
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => $message,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ],
        );
        $this->assertResponseIsSuccessful();
    }

    private function assertReturnSucceeded(): void
    {
        $container = $this->client->getContainer();
        $replies = array_values(array_filter(
            $container->get(DebugSmsSender::class)->getSentMessages(),
            static fn (array $m): bool => $m['number'] === self::USER_PHONE_NUMBER
        ));
        $this->assertCount(1, $replies);
        $this->assertInstanceOf(TranslatableResult::class, $replies[0]['message']);
        $this->assertSame('bike.return.success', $replies[0]['message']->getCode());

        $bike = $container->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $this->assertNull($bike['userId']);
        $this->assertSame(self::STAND_NAME, $bike['standName']);
    }

    /**
     * @return list<array{number: string, message: TranslatableMessage}>
     */
    private function noteAlertSms(): array
    {
        return array_values(array_filter(
            $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages(),
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'bike.note.admin.notification'
        ));
    }

    /**
     * Admins in findAdmins() order, which is the order the Notifier sends in.
     *
     * @return list<array{userId: int|string, number: string, mail: string}>
     */
    private function admins(): array
    {
        return $this->client->getContainer()->get(UserRepository::class)->findAdmins();
    }
}
