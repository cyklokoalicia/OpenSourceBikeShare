<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Command;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class InactiveStandBikesCheckCommandTest extends BikeSharingKernelTestCase
{
    use ClockSensitiveTrait;

    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_INACTIVE_SHORT = 21;
    private const BIKE_INACTIVE_LONG = 22;
    private const BIKE_ON_SERVICE_STAND = 23;
    private const BIKE_ON_HIDDEN_STAND = 24;
    private const BIKE_ON_VIRTUAL_STAND = 25;
    private const STAND1_NAME = 'STAND1';
    private const STAND2_NAME = 'STAND2';
    private const SERVICE_STAND_NAME = 'SERVICE_STAND';
    private const HIDDEN_STAND_NAME = 'HIDDEN_STAND';
    private const VIRTUAL_STAND_NAME = 'VIRTUAL_STAND';

    private int $adminUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $this->adminUserId = (int)$admin['userId'];

        $this->simulateBikeActivity(self::BIKE_INACTIVE_SHORT, self::STAND1_NAME, '2030-02-05 10:00:00');
        $this->simulateBikeActivity(self::BIKE_INACTIVE_LONG, self::STAND2_NAME, '2030-01-15 10:00:00');
        $this->simulateBikeActivity(self::BIKE_ON_SERVICE_STAND, self::SERVICE_STAND_NAME, '2030-01-20 10:00:00');
        $this->simulateBikeActivity(self::BIKE_ON_HIDDEN_STAND, self::HIDDEN_STAND_NAME, '2030-01-25 10:00:00');
        $this->simulateBikeActivity(self::BIKE_ON_VIRTUAL_STAND, self::VIRTUAL_STAND_NAME, '2030-01-30 10:00:00');
        static::mockTime('2030-02-15 12:00:00');
    }

    protected function tearDown(): void
    {
        $this->parkTestBikesAtServiceStand();
        static::mockTime();
        parent::tearDown();
    }

    public function testCommandSendsEmailWithSingleSortedListAndSkipsServiceStand(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:inactive_stand_bikes_check');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $commandTester->assertCommandIsSuccessful();

        $mailSender = self::getContainer()->get(MailSenderInterface::class);
        $this->assertCount(1, $mailSender->getSentMessages(), 'Invalid number of sent emails');
        $message = $mailSender->getSentMessages()[0]['message'];

        $this->assertStringContainsString(
            'Inactive bikes on stands (service stands excluded, sorted by inactive days).',
            $message
        );
        $this->assertStringContainsString(self::BIKE_INACTIVE_SHORT . ' | STAND1', $message);
        $this->assertStringContainsString(self::BIKE_INACTIVE_LONG . ' | STAND2', $message);
        $this->assertLessThan(
            strpos($message, self::BIKE_INACTIVE_LONG . ' | STAND2'),
            strpos($message, self::BIKE_INACTIVE_SHORT . ' | STAND1')
        );

        $this->assertStringContainsString(
            self::BIKE_ON_HIDDEN_STAND . ' | ' . self::HIDDEN_STAND_NAME,
            $message,
            'Bikes on hidden stands should be reported'
        );
        $this->assertStringContainsString(
            self::BIKE_ON_VIRTUAL_STAND . ' | ' . self::VIRTUAL_STAND_NAME,
            $message,
            'Bikes on virtual stands should be reported'
        );
        $this->assertStringNotContainsString('- ' . self::BIKE_ON_SERVICE_STAND . ' |', $message);

        $smsConnector = self::getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount(0, $smsConnector->getSentMessages(), 'SMS should not be sent to admins');
    }

    public function testCommandQuietModePrintsNoOutput(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:inactive_stand_bikes_check');
        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);
        $commandTester->assertCommandIsSuccessful();

        $this->assertSame('', trim($commandTester->getDisplay()));

        $mailSender = self::getContainer()->get(MailSenderInterface::class);
        $this->assertCount(1, $mailSender->getSentMessages(), 'Email should still be sent in silent mode');
    }

    private function simulateBikeActivity(int $bikeNumber, string $standName, string $lastReturnTime): void
    {
        $rentSystem = self::getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $returnTime = new \DateTimeImmutable($lastReturnTime);

        static::mockTime($returnTime->sub(new \DateInterval('PT2M'))->format('Y-m-d H:i:s'));
        $rentSystem->returnBike($this->adminUserId, $bikeNumber, $standName, '', true);

        static::mockTime($returnTime->sub(new \DateInterval('PT1M'))->format('Y-m-d H:i:s'));
        $rentSystem->rentBike($this->adminUserId, $bikeNumber, true);

        static::mockTime($returnTime->format('Y-m-d H:i:s'));
        $rentSystem->returnBike($this->adminUserId, $bikeNumber, $standName, '', true);
    }

    private function parkTestBikesAtServiceStand(): void
    {
        static::mockTime('2000-01-01 00:00:00');

        $rentSystem = self::getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $bikes = [
            self::BIKE_INACTIVE_SHORT,
            self::BIKE_INACTIVE_LONG,
            self::BIKE_ON_SERVICE_STAND,
            self::BIKE_ON_HIDDEN_STAND,
            self::BIKE_ON_VIRTUAL_STAND,
        ];
        foreach ($bikes as $bikeNumber) {
            $rentSystem->returnBike($this->adminUserId, $bikeNumber, self::SERVICE_STAND_NAME, '', true);
        }
    }
}
