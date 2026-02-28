<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Report;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;

class InactiveBikesReportTest extends BikeSharingWebTestCase
{
    use ClockSensitiveTrait;

    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_INACTIVE_SHORT = 24;
    private const BIKE_INACTIVE_LONG = 25;
    private const BIKE_ON_SERVICE_STAND = 20;
    private const STAND1_NAME = 'STAND1';
    private const STAND2_NAME = 'STAND2';
    private const SERVICE_STAND_NAME = 'SERVICE_STAND';

    private int $adminUserId;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $this->adminUserId = (int)$admin['userId'];
    }

    protected function tearDown(): void
    {
        $this->parkTestBikesAtServiceStand();
        static::mockTime();
        parent::tearDown();
    }

    public function testInactiveBikesReport(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->simulateBikeActivity(self::BIKE_INACTIVE_SHORT, self::STAND1_NAME, '2024-02-01 10:00:00');
        $this->simulateBikeActivity(self::BIKE_INACTIVE_LONG, self::STAND2_NAME, '2024-01-15 10:00:00');
        $this->simulateBikeActivity(self::BIKE_ON_SERVICE_STAND, self::SERVICE_STAND_NAME, '2024-01-20 10:00:00');
        static::mockTime('2024-02-15 12:00:00');

        $db = $this->client->getContainer()->get(DbInterface::class);

        $receivedLogsBefore = (int)$db->query(
            'SELECT COUNT(*) AS total
             FROM received
             WHERE sender = :sender
               AND sms_text = :uri',
            [
                'sender' => self::ADMIN_PHONE_NUMBER,
                'uri' => '/api/v1/admin/reports/inactive-bikes',
            ]
        )->fetchAssoc()['total'];

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/reports/inactive-bikes');
        $this->assertResponseIsSuccessful();

        $receivedLogsAfter = (int)$db->query(
            'SELECT COUNT(*) AS total
             FROM received
             WHERE sender = :sender
               AND sms_text = :uri',
            [
                'sender' => self::ADMIN_PHONE_NUMBER,
                'uri' => '/api/v1/admin/reports/inactive-bikes',
            ]
        )->fetchAssoc()['total'];
        $this->assertSame($receivedLogsBefore + 1, $receivedLogsAfter);

        $responseData = $this->decodeApiResponseData();

        $this->assertGreaterThanOrEqual(2, count($responseData));

        foreach ($responseData as $row) {
            $this->assertArrayHasKey('bikeNum', $row);
            $this->assertArrayHasKey('standName', $row);
            $this->assertArrayHasKey('lastMoveTime', $row);
            $this->assertArrayHasKey('inactiveDays', $row);
            $this->assertArrayNotHasKey('severity', $row);
        }

        $shortInactiveBike = $this->findBikeReportRow($responseData, self::BIKE_INACTIVE_SHORT);
        $longInactiveBike = $this->findBikeReportRow($responseData, self::BIKE_INACTIVE_LONG);
        $serviceStandBike = $this->findBikeReportRow($responseData, self::BIKE_ON_SERVICE_STAND);

        $this->assertNotNull($shortInactiveBike);
        $this->assertNotNull($longInactiveBike);
        $this->assertNull($serviceStandBike);
        $this->assertSame(self::STAND1_NAME, $shortInactiveBike['standName']);
        $this->assertSame(self::STAND2_NAME, $longInactiveBike['standName']);
        $this->assertGreaterThanOrEqual(7, (int)$shortInactiveBike['inactiveDays']);
        $this->assertGreaterThan((int)$shortInactiveBike['inactiveDays'], (int)$longInactiveBike['inactiveDays']);
    }

    private function simulateBikeActivity(int $bikeNumber, string $standName, string $lastReturnTime): void
    {
        $rentSystem = $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        $returnTime = new \DateTimeImmutable($lastReturnTime);

        static::mockTime($returnTime->sub(new \DateInterval('PT2M'))->format('Y-m-d H:i:s'));
        $rentSystem->returnBike($this->adminUserId, $bikeNumber, $standName, '', true);

        static::mockTime($returnTime->sub(new \DateInterval('PT1M'))->format('Y-m-d H:i:s'));
        $rentSystem->rentBike($this->adminUserId, $bikeNumber, true);

        static::mockTime($returnTime->format('Y-m-d H:i:s'));
        $rentSystem->returnBike($this->adminUserId, $bikeNumber, $standName, '', true);
    }

    private function findBikeReportRow(array $responseData, int $bikeNumber): ?array
    {
        foreach ($responseData as $row) {
            if ((int)$row['bikeNum'] === $bikeNumber) {
                return $row;
            }
        }

        return null;
    }

    private function parkTestBikesAtServiceStand(): void
    {
        static::mockTime('2000-01-01 00:00:00');

        $rentSystem = $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB);
        foreach ([self::BIKE_INACTIVE_SHORT, self::BIKE_INACTIVE_LONG, self::BIKE_ON_SERVICE_STAND] as $bikeNumber) {
            $rentSystem->returnBike($this->adminUserId, $bikeNumber, self::SERVICE_STAND_NAME, '', true);
        }
    }
}
