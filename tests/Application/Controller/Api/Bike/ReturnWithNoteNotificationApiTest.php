<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

class ReturnWithNoteNotificationApiTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 6;
    private const STAND_NAME = 'STAND1';

    protected function setUp(): void
    {
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        parent::setUp();

        $container = $this->client->getContainer();
        $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem = $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $rentSystem->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);
        $user = $container->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $rentSystem->rentBike($user['userId'], self::BIKE_NUMBER);

        $container->get(MailSenderInterface::class)->reset();
    }

    protected function tearDown(): void
    {
        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        parent::tearDown();
    }

    public function testApiReturnWithNoteAndFailingAdminMailStillSucceeds(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');

        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $admins = array_values(array_filter(
            $this->client->getContainer()->get(UserRepository::class)->findAdmins(),
            static fn (array $a): bool => !empty($a['mail'])
        ));
        $failing = $admins[0]['mail'];
        $mailSender = $this->client->getContainer()->get(MailSenderInterface::class);
        $mailSender->failFor($failing);

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/returns',
            [
                'bikeNumber' => self::BIKE_NUMBER,
                'standName' => self::STAND_NAME,
                'note' => 'Bike returned from api notification test',
            ]
        );
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertFalse($response['error']);
        $this->assertSame('bike.return.success', $response['code']);

        $recipients = array_column(
            $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages(),
            'recipient'
        );
        $expected = array_values(array_diff(array_column($admins, 'mail'), [$failing]));
        $this->assertEqualsCanonicalizing($expected, $recipients);
    }
}
