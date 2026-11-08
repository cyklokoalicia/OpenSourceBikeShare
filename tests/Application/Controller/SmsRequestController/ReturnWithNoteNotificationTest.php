<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use BikeShare\Translation\TranslatableResult;
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
        $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem = $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS);
        $rentSystem->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        $user = $container->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $rentSystem->rentBike($user['userId'], self::BIKE_NUMBER);

        $container->get(DebugSmsSender::class)->reset();
        $container->get(MailSenderInterface::class)->reset();
    }

    public function testReturnWithNoteNotifiesAdmins(): void
    {
        $this->sendReturn(self::NOTE);

        $admins = $this->adminsWithNumber();
        $sms = $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages();
        $this->assertCount(1 + count($admins), $sms);

        $notes = array_values(array_filter(
            $sms,
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'bike.note.admin.notification'
        ));
        $this->assertCount(count($admins), $notes);
        $params = $notes[0]['message']->getParameters();
        $this->assertIsInt($params['noteId']);
        $this->assertGreaterThan(0, $params['noteId']);
        $this->assertSame(self::NOTE, $params['userNote']);
        $this->assertSame(self::BIKE_NUMBER, $params['bikeNumber']);
        $this->assertInstanceOf(TranslatableMessage::class, $params['bikeStatus']);
        $this->assertSame('bike.status.at_stand', $params['bikeStatus']->getMessage());
        $this->assertSame(['standName' => self::STAND_NAME], $params['bikeStatus']->getParameters());
        $this->assertArrayHasKey('userName', $params);
        $this->assertArrayHasKey('phone', $params);

        $mails = $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(count($this->adminsWithMail()), $mails);
    }

    public function testReturnWithoutNoteSendsNoAdminNotification(): void
    {
        $this->sendReturn(null);

        $sms = $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages();
        $this->assertCount(1, $sms);
        $this->assertSame(self::USER_PHONE_NUMBER, $sms[0]['number']);
        $this->assertCount(0, $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages());
    }

    public function testAdminSmsFailureDoesNotBreakReturn(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');
        $admins = $this->adminsWithNumber();
        $failing = $admins[0]['number'];
        $this->client->getContainer()->get(DebugSmsSender::class)->failFor($failing);

        $this->sendReturn(self::NOTE);

        $sms = $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages();
        $userReplies = array_values(array_filter(
            $sms,
            static fn (array $m): bool => $m['number'] === self::USER_PHONE_NUMBER
        ));
        $this->assertCount(1, $userReplies);
        $reply = $userReplies[0]['message'];
        $this->assertInstanceOf(TranslatableResult::class, $reply);
        $this->assertSame('bike.return.success', $reply->getCode());

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $stand = $this->client->getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);
        $this->assertNull($bike['userId']);
        $this->assertSame($stand['standName'], $bike['standName']);

        $adminNumbers = array_column(array_filter(
            $sms,
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'bike.note.admin.notification'
        ), 'number');
        $expected = array_values(array_diff(array_column($admins, 'number'), [$failing]));
        $this->assertEqualsCanonicalizing($expected, $adminNumbers);
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

    private function adminsWithNumber(): array
    {
        return array_values(array_filter(
            $this->client->getContainer()->get(UserRepository::class)->findAdmins(),
            static fn (array $a): bool => !empty($a['number'])
        ));
    }

    private function adminsWithMail(): array
    {
        return array_values(array_filter(
            $this->client->getContainer()->get(UserRepository::class)->findAdmins(),
            static fn (array $a): bool => !empty($a['mail'])
        ));
    }
}
