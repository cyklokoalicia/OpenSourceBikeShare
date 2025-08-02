<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Command;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

class LongRentalCheckCommandTest extends BikeSharingKernelTestCase
{
    use ClockSensitiveTrait;

    private const USER_PHONE_NUMBER = '421444444444';
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 6;
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

        self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
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
        self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );

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
        self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
            ->rentBike($user['userId'], self::BIKE_NUMBER);

        static::mockTime('+' . $_ENV['WATCHES_LONG_RENTAL'] . ' hours 15 minutes');

        $application = new Application(self::$kernel);

        $command = $application->find('app:long_rental_check');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $smsConnector = self::getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount(2, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessages = $smsConnector->getSentMessages();
        foreach ($sentMessages as $sentMessage) {
            if ($sentMessage['number'] === self::USER_PHONE_NUMBER) {
                $this->assertStringContainsString(
                    'Please, return your bike ' . self::BIKE_NUMBER . ' immediately to the closest stand!',
                    $sentMessage['text'],
                    'Invalid message for user'
                );
            } elseif ($sentMessage['number'] === self::ADMIN_PHONE_NUMBER) {
                $this->assertStringContainsString(
                    'Bike rental exceed ' . $_ENV['WATCHES_LONG_RENTAL'] . ' hours',
                    $sentMessage['text'],
                    'Invalid message for admin'
                );
            }
        }

        $mailSender = self::getContainer()->get(MailSenderInterface::class);
        $this->assertCount(1, $mailSender->getSentMessages(), 'Invalid number of sent email');
        $sentMessage = $mailSender->getSentMessages()[0];
        $this->assertStringContainsString(
            'Bike rental exceed ' . $_ENV['WATCHES_LONG_RENTAL'] . ' hours',
            $sentMessage['message'],
            'Invalid message for admin email'
        );
        $this->assertStringContainsString(
            self::USER_PHONE_NUMBER,
            $sentMessage['message'],
            'Invalid message for admin email'
        );
    }
}
