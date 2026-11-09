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
    /** Fixture stand whose bikes were never returned there, so its stack top is unknown. */
    private const FRESH_STAND_NAME = 'FRESH_STAND';
    private const FRESH_STAND_BIKE = 26;

    private ?int $otherBike = null;
    private bool $rentedFromFreshStand = false;

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
        $container = $this->client->getContainer();
        $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $rentSystem = $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        if ($this->otherBike !== null) {
            $rentSystem->returnBike($admin['userId'], $this->otherBike, self::STAND_NAME, '', true);
        }
        $rentSystem->returnBike($admin['userId'], self::STACK_TOP_BIKE, self::STAND_NAME, '', true);
        if ($this->rentedFromFreshStand) {
            $this->restoreFreshStand((int)$admin['userId']);
        }

        parent::tearDown();
    }

    public function testRentingBikeBelowStackTopNotifiesAdminsByMail(): void
    {
        $this->rentAsUser($this->otherBike);

        $container = $this->client->getContainer();
        $user = $container->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
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
            $this->assertSame('Bike rented out of stack order', $mail['subject']);
            $this->assertSame(
                sprintf(
                    'Bike %d rented out of stack by %s. %d was on the top of the stack at %s.',
                    $this->otherBike,
                    $user['userName'],
                    self::STACK_TOP_BIKE,
                    self::STAND_NAME
                ),
                $mail['message']
            );
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
        $this->rentedFromFreshStand = true;
        $this->rentAsUser(self::FRESH_STAND_BIKE);

        $container = $this->client->getContainer();
        $this->assertSame([], $container->get(MailSenderInterface::class)->getSentMessages());
        $this->assertSame([], $container->get(DebugSmsSender::class)->getSentMessages());
    }

    /**
     * Puts the fresh-stand bike back and drops its history, so the stand's stack top stays unknown.
     */
    private function restoreFreshStand(int $adminUserId): void
    {
        $container = $this->client->getContainer();
        $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike($adminUserId, self::FRESH_STAND_BIKE, self::FRESH_STAND_NAME, '', true);
        $container->get(DbInterface::class)->query(
            'DELETE FROM history WHERE bikeNum = :bikeNumber',
            ['bikeNumber' => self::FRESH_STAND_BIKE]
        );
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
