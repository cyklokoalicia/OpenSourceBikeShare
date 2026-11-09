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

    /** @var array{bike: int, standName: string, maxHistoryId: int}|null */
    private ?array $rented = null;

    protected function setUp(): void
    {
        $this->setEnvVar('FORCE_STACK', '1');
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->rented !== null) {
            $container = $this->client->getContainer();
            $admin = $container->get(UserRepository::class)->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
            $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
                ->returnBike($admin['userId'], $this->rented['bike'], $this->rented['standName'], '', true);
            // Drop this test's rent/return rows so the stand's stack top is as before.
            $container->get(DbInterface::class)->query(
                'DELETE FROM history WHERE id > :maxId',
                ['maxId' => $this->rented['maxHistoryId']]
            );
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
        $this->assertStringContainsString((string)self::STACK_TOP_BIKE, $this->decodeJsonResponse()['detail']);
    }

    public function testBikeOnAStandWithUnknownStackTopCanBeRented(): void
    {
        $container = $this->client->getContainer();
        $db = $container->get(DbInterface::class);
        $standRepository = $container->get(StandRepository::class);
        $stands = $db->query("SELECT standId, standName FROM stands WHERE status = 'active'")->fetchAllAssoc();
        foreach ($stands as $stand) {
            $bikes = $standRepository->findBikesOnStand((int)$stand['standId']);
            if ($bikes !== [] && $standRepository->findLastReturnedBikeOnStand((int)$stand['standId']) === null) {
                $this->rented = [
                    'bike' => (int)$bikes[0]['bikeNum'],
                    'standName' => $stand['standName'],
                    'maxHistoryId' => (int)$db->query('SELECT COALESCE(MAX(id), 0) AS maxId FROM history')
                        ->fetchAssoc()['maxId'],
                ];
                break;
            }
        }
        $this->assertNotNull($this->rented, 'Fixtures need an active stand with bikes and no returns');

        $this->rentAsUser($this->rented['bike']);

        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertFalse($response['error'], json_encode($response));
        $this->assertSame('bike.rent.success', $response['code']);
    }

    private function rentAsUser(int $bikeNumber): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_POST, '/api/v1/rentals', ['bikeNumber' => $bikeNumber]);
    }
}
