<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class StackWatchNotificationTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const STACK_TOP_BIKE = 6;
    private const STAND_NAME = 'STAND1';

    private int $otherBike;

    /** @var array{bike: int, standName: string, maxHistoryId: int}|null */
    private ?array $unknownTopRental = null;

    protected function setUp(): void
    {
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        parent::setUp();

        $container = $this->client->getContainer();
        $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike($admin['userId'], self::STACK_TOP_BIKE, self::STAND_NAME, '', true);

        $standRepository = $container->get(StandRepository::class);
        $stand = $standRepository->findItemByName(self::STAND_NAME);
        $this->assertSame(self::STACK_TOP_BIKE, $standRepository->findLastReturnedBikeOnStand((int)$stand['standId']));
        $others = array_values(array_filter(
            array_map('intval', array_column($standRepository->findBikesOnStand((int)$stand['standId']), 'bikeNum')),
            static fn (int $bike): bool => $bike !== self::STACK_TOP_BIKE
        ));
        $this->assertNotEmpty($others, 'Fixture stand needs at least two bikes');
        $this->otherBike = $others[0];

        $container->get(MailSenderInterface::class)->reset();
        $container->get(DebugSmsSender::class)->reset();
    }

    protected function tearDown(): void
    {
        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem = $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $rentSystem->returnBike($admin['userId'], $this->otherBike, self::STAND_NAME, '', true);
        $rentSystem->returnBike($admin['userId'], self::STACK_TOP_BIKE, self::STAND_NAME, '', true);
        if ($this->unknownTopRental !== null) {
            $rentSystem->returnBike(
                $admin['userId'],
                $this->unknownTopRental['bike'],
                $this->unknownTopRental['standName'],
                '',
                true
            );
            // Drop this test's rent/return rows so the stand's stack top stays unknown for later tests.
            $this->client->getContainer()->get(DbInterface::class)->query(
                'DELETE FROM history WHERE id > :maxId',
                ['maxId' => $this->unknownTopRental['maxHistoryId']]
            );
        }

        parent::tearDown();
    }

    public function testRentingBikeBelowStackTopNotifiesAdminsByMail(): void
    {
        $this->rentAsUser($this->otherBike);

        $container = $this->client->getContainer();
        $adminMails = array_column(
            array_filter(
                $container->get(UserRepository::class)->findAdmins(),
                static fn (array $a): bool => !empty($a['mail'])
            ),
            'mail'
        );
        $this->assertNotEmpty($adminMails);

        $sent = $container->get(MailSenderInterface::class)->getSentMessages();
        $this->assertEqualsCanonicalizing($adminMails, array_column($sent, 'recipient'));
        foreach ($sent as $mail) {
            $this->assertStringContainsString((string)$this->otherBike, (string)$mail['message']);
            $this->assertStringContainsString((string)self::STACK_TOP_BIKE, (string)$mail['message']);
            $this->assertSame('Bike rented out of stack order', $mail['subject']);
        }
        $this->assertSame([], $container->get(DebugSmsSender::class)->getSentMessages());
    }

    public function testRentingStackTopSendsNothing(): void
    {
        $this->rentAsUser(self::STACK_TOP_BIKE);

        $container = $this->client->getContainer();
        $this->assertSame([], $container->get(MailSenderInterface::class)->getSentMessages());
        $this->assertSame([], $container->get(DebugSmsSender::class)->getSentMessages());
    }

    public function testRentingOnStandWithUnknownStackTopSendsNothing(): void
    {
        $container = $this->client->getContainer();
        $db = $container->get(DbInterface::class);
        $standRepository = $container->get(StandRepository::class);
        $stands = $db->query("SELECT standId, standName FROM stands WHERE status = 'active'")->fetchAllAssoc();
        foreach ($stands as $stand) {
            $bikes = $standRepository->findBikesOnStand((int)$stand['standId']);
            if ($bikes !== [] && $standRepository->findLastReturnedBikeOnStand((int)$stand['standId']) === null) {
                $this->unknownTopRental = [
                    'bike' => (int)$bikes[0]['bikeNum'],
                    'standName' => $stand['standName'],
                    'maxHistoryId' => (int)$db->query('SELECT COALESCE(MAX(id), 0) AS maxId FROM history')
                        ->fetchAssoc()['maxId'],
                ];
                break;
            }
        }
        $this->assertNotNull($this->unknownTopRental, 'Fixtures need an active stand with bikes and no returns');

        $this->rentAsUser($this->unknownTopRental['bike']);

        $container = $this->client->getContainer();
        $this->assertSame([], $container->get(MailSenderInterface::class)->getSentMessages());
        $this->assertSame([], $container->get(DebugSmsSender::class)->getSentMessages());
    }

    private function rentAsUser(int $bikeNumber): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_POST, '/api/v1/rentals', ['bikeNumber' => $bikeNumber]);
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertSame('bike.rent.success', $response['code']);
    }
}
