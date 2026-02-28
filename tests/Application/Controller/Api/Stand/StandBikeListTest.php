<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\Rent\Enum\RentSystemType;
use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\App\Security\UserProvider;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class StandBikeListTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 6;
    private const STAND_NAME = 'STAND1';

    private $forceStack;

    protected function setup(): void
    {
        $this->forceStack = $_ENV['FORCE_STACK'];
        parent::setup();
    }

    protected function tearDown(): void
    {
        $_ENV['FORCE_STACK'] = $this->forceStack;
        parent::tearDown();
    }

    #[DataProvider('bikeListOnStandDataProvider')]
    public function testBikeListOnStand(
        bool $forceStack,
        $expectedStackTopBike
    ): void {
        $_ENV['FORCE_STACK'] = $forceStack ? '1' : '0';

        #add bike to stand with note
        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                'Note for stand api test',
                true
            );

        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/v1/stands/' . self::STAND_NAME . '/bikes');
        $this->assertResponseIsSuccessful();
        $data = $this->decodeApiResponseData();
        $this->assertEquals($expectedStackTopBike, $data['stackTopBike']);
        foreach ($data['bikesOnStand'] as $bike) {
            $this->assertArrayHasKey('bikeNum', $bike);
            $this->assertArrayHasKey('notes', $bike);
            if ($bike['bikeNum'] === self::BIKE_NUMBER) {
                $this->assertStringContainsString('Note for stand api test', $bike['notes']);
            }
        }
    }

    public static function bikeListOnStandDataProvider(): iterable
    {
        yield 'forceStack true' => [
            'forceStack' => true,
            'expectedStackTopBike' => self::BIKE_NUMBER,
        ];
        yield 'forceStack false' => [
            'forceStack' => false,
            'expectedStackTopBike' => false,
        ];
    }
}
