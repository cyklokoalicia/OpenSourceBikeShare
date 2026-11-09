<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class ReturnWithNoteNotificationApiTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 6;
    private const STAND_NAME = 'STAND1';
    private const NOTE = 'Bike returned from api notification test';

    protected function setUp(): void
    {
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        parent::setUp();

        $container = $this->client->getContainer();
        $userRepository = $container->get(UserRepository::class);
        $admin = $userRepository->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem = $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $rentSystem->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);
        $user = $userRepository->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $rent = $rentSystem->rentBike($user['userId'], self::BIKE_NUMBER);
        $this->assertFalse($rent->isError(), 'Setup rent failed: ' . $rent->getCode());

        // Fixtures have a single admin; make a second one so isolation can be asserted on a survivor.
        $container->get(DbInterface::class)->query(
            'UPDATE users SET privileges = 3 WHERE userId = :userId',
            ['userId' => $admin['userId']]
        );

        $container->get(MailSenderInterface::class)->reset();
        $container->get(DebugSmsSender::class)->reset();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $container->get(DbInterface::class)->query(
            'UPDATE users SET privileges = 1 WHERE userId = :userId',
            ['userId' => $admin['userId']]
        );
        $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        parent::tearDown();
    }

    public function testApiReturnWithNoteAndFailingAdminMailStillSucceeds(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');

        $container = $this->client->getContainer();
        $admins = $container->get(UserRepository::class)->findAdmins();
        $this->assertCount(2, $admins);
        $failing = $admins[0]['mail'];
        $container->get(MailSenderInterface::class)->failFor($failing);

        $user = $container->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/returns',
            ['bikeNumber' => self::BIKE_NUMBER, 'standName' => self::STAND_NAME, 'note' => self::NOTE]
        );

        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertFalse($response['error']);
        $this->assertSame('bike.return.success', $response['code']);

        $container = $this->client->getContainer();
        $bike = $container->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $this->assertNull($bike['userId']);
        $this->assertSame(self::STAND_NAME, $bike['standName']);

        $this->assertSame(
            [$admins[1]['mail']],
            array_column($container->get(MailSenderInterface::class)->getSentMessages(), 'recipient'),
            'The admin after the failing one must still get the note mail'
        );

        $noteSms = array_values(array_filter(
            $container->get(DebugSmsSender::class)->getSentMessages(),
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'bike.note.admin.notification'
        ));
        $this->assertEqualsCanonicalizing(array_column($admins, 'number'), array_column($noteSms, 'number'));
        foreach ($noteSms as $sms) {
            $this->assertSame(self::NOTE, $sms['message']->getParameters()['userNote']);
        }
    }
}
