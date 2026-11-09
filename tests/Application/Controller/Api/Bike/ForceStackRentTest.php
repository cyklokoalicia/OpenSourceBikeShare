<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ForceStackRentTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const STACK_TOP_BIKE = 6;
    private const STAND_NAME = 'STAND1';
    /** Fixture stand whose bikes were never returned there, so its stack top is unknown. */
    private const FRESH_STAND_NAME = 'FRESH_STAND';
    private const FRESH_STAND_BIKE = 26;

    private bool $rentedFromFreshStand = false;

    protected function setUp(): void
    {
        $this->setEnvVar('FORCE_STACK', '1');
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->rentedFromFreshStand) {
            $admin = $this->client->getContainer()->get(UserRepository::class)
                ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
            $this->restoreFreshStand((int)$admin['userId']);
        }

        parent::tearDown();
    }

    public function testBikeBelowAKnownStackTopCannotBeRented(): void
    {
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

        $this->rentAsUser($others[0]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertSame(
            sprintf(
                'Bike %d is not rentable now, you have to rent bike %d from this stand.',
                $others[0],
                self::STACK_TOP_BIKE
            ),
            $this->decodeJsonResponse()['detail']
        );
    }

    public function testBikeOnAStandWithUnknownStackTopCanBeRented(): void
    {
        $this->rentedFromFreshStand = true;
        $this->rentAsUser(self::FRESH_STAND_BIKE);

        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertSame('bike.rent.success', $response['code']);
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
    }
}
