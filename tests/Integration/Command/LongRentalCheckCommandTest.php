<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Command;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\TranslatableMessage;

class LongRentalCheckCommandTest extends BikeSharingKernelTestCase
{
    use ClockSensitiveTrait;

    private const USER_PHONE_NUMBER = '421951444444';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 6;
    private const SECOND_USER_PHONE_NUMBER = '421951111111';
    private const SECOND_BIKE_NUMBER = 7;
    private const STAND_NAME = 'STAND1';

    private $watchesTooMany;
    private $notifyUserAboutLongRent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->watchesTooMany = $_ENV['WATCHES_NUMBER_TOO_MANY'];
        $this->notifyUserAboutLongRent = $_ENV['NOTIFY_USER_ABOUT_LONG_RENTAL'];

        $admin = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        self::getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );
    }

    protected function tearDown(): void
    {
        $admin = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem = self::getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $rentSystem->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);
        $rentSystem->returnBike($admin['userId'], self::SECOND_BIKE_NUMBER, self::STAND_NAME, '', true);
        self::getContainer()->get(DebugSmsSender::class)->reset();

        $_ENV['WATCHES_NUMBER_TOO_MANY'] = $this->watchesTooMany;
        $_ENV['NOTIFY_USER_ABOUT_LONG_RENTAL'] = $this->notifyUserAboutLongRent;
        parent::tearDown();
    }

    public function testCommand(): void
    {
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 999;
        $_ENV['NOTIFY_USER_ABOUT_LONG_RENTAL'] = 1;
        static::mockTime();

        $user = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        self::getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->rentBike($user['userId'], self::BIKE_NUMBER);

        static::mockTime('+' . $_ENV['WATCHES_LONG_RENTAL'] . ' hours 15 minutes');

        $application = new Application(self::$kernel);

        $command = $application->find('app:long_rental_check');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $admins = self::getContainer()->get(UserRepository::class)->findAdmins();
        $smsConnector = self::getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount(1 + count($admins), $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessages = $smsConnector->getSentMessages();
        foreach ($sentMessages as $sentMessage) {
            if ($sentMessage['number'] === self::USER_PHONE_NUMBER) {
                $this->assertStringContainsString(
                    'Please, return your bike ' . self::BIKE_NUMBER . ' immediately to the closest stand!',
                    $sentMessage['text'],
                    'Invalid message for user'
                );
            } else {
                $this->assertContains($sentMessage['number'], array_column($admins, 'number'), 'Unexpected recipient');
                $this->assertStringContainsString(
                    'Bike rental exceed ' . $_ENV['WATCHES_LONG_RENTAL'] . ' hours',
                    $sentMessage['text'],
                    'Invalid message for admin'
                );
            }
        }

        $mailSender = self::getContainer()->get(MailSenderInterface::class);
        $this->assertCount(count($admins), $mailSender->getSentMessages(), 'Invalid number of sent email');
        $sentMessage = $mailSender->getSentMessages()[0];
        $this->assertEquals(new TranslatableMessage('notification.subject.long_rental_admin'), $sentMessage['subject']);
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('admin.notification.long_rental', $sentMessage['message']->getMessage());
        $params = $sentMessage['message']->getParameters();
        $this->assertEquals($_ENV['WATCHES_LONG_RENTAL'], $params['hour'], 'Invalid hour count for admin email');
        $this->assertStringContainsString(
            self::USER_PHONE_NUMBER,
            $params['abusers'],
            'Invalid message for admin email'
        );
    }

    public function testFailedWarningToOneRiderDoesNotStopTheRun(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');
        $_ENV['WATCHES_NUMBER_TOO_MANY'] = 999;
        $_ENV['NOTIFY_USER_ABOUT_LONG_RENTAL'] = 1;
        static::mockTime();

        $container = self::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $rentSystem = $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $first = $userRepository->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $second = $userRepository->findItemByPhoneNumber(self::SECOND_USER_PHONE_NUMBER);
        $admin = $userRepository->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem->returnBike($admin['userId'], self::SECOND_BIKE_NUMBER, self::STAND_NAME, '', true);
        $this->assertFalse($rentSystem->rentBike($first['userId'], self::BIKE_NUMBER)->isError());
        $this->assertFalse($rentSystem->rentBike($second['userId'], self::SECOND_BIKE_NUMBER)->isError());

        // Riders are processed by bike number, so the first warning is the one that fails.
        $container->get(DebugSmsSender::class)->failFor(self::USER_PHONE_NUMBER);
        static::mockTime('+' . $_ENV['WATCHES_LONG_RENTAL'] . ' hours 15 minutes');

        $commandTester = new CommandTester((new Application(self::$kernel))->find('app:long_rental_check'));
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $sms = $container->get(SmsConnectorInterface::class)->getSentMessages();
        $warnings = array_values(array_filter(
            $sms,
            static fn (array $m): bool => str_contains($m['text'], 'Please, return your bike')
        ));
        $this->assertSame([self::SECOND_USER_PHONE_NUMBER], array_column($warnings, 'number'));
        $this->assertStringContainsString('return your bike ' . self::SECOND_BIKE_NUMBER . ' ', $warnings[0]['text']);

        $summarySubject = new TranslatableMessage('notification.subject.long_rental_admin');
        $summaries = array_values(array_filter(
            $container->get(MailSenderInterface::class)->getSentMessages(),
            static fn (array $m): bool => $m['subject'] == $summarySubject
        ));
        $this->assertCount(count($userRepository->findAdmins()), $summaries);
        $this->assertInstanceOf(TranslatableMessage::class, $summaries[0]['message']);
        $abusers = $summaries[0]['message']->getParameters()['abusers'];
        $this->assertStringContainsString(self::USER_PHONE_NUMBER, $abusers);
        $this->assertStringContainsString(self::SECOND_USER_PHONE_NUMBER, $abusers);
    }
}
