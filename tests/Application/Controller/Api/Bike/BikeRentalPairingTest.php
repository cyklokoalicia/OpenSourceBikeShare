<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class BikeRentalPairingTest extends BikeSharingWebTestCase
{
    use ClockSensitiveTrait;

    private const BIKE_NUMBER = 6;
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const STAND_NAME = 'STAND1';
    private DbInterface $db;
    private int $userId;
    private int $initialHistoryId;

    protected function setUp(): void
    {
        static::mockTime('2026-09-11 12:00:00');
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        parent::setUp();
        $this->db = $this->client->getContainer()->get(DbInterface::class);
        $this->login(self::ADMIN_PHONE_NUMBER);
        $this->returnBike(true);
        $this->initialHistoryId = (int)$this->db->query(
            'SELECT id FROM history WHERE bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            ['bikeNum' => self::BIKE_NUMBER],
        )->fetchAssoc()['id'];
        $this->userId = $this->login(self::USER_PHONE_NUMBER);
    }

    public function testNewCyclesAtTheSameTimeHaveSeparatePairsAndLeaveStartsUnchanged(): void
    {
        $rentIds = [];
        for ($cycle = 0; $cycle < 2; ++$cycle) {
            $this->client->request('POST', '/api/v1/rentals', ['bikeNumber' => self::BIKE_NUMBER]);
            self::assertResponseIsSuccessful();
            $start = $this->history()[$cycle * 2];
            $rentIds[] = $start['id'];
            $this->returnBike();
            self::assertSame($start, $this->history()[$cycle * 2]);
        }
        $rows = $this->history();
        self::assertSame(['RENT', 'RETURN', 'RENT', 'RETURN'], array_column($rows, 'action'));
        self::assertSame([null, $rentIds[0], null, $rentIds[1]], array_column($rows, 'pairActionId'));
        self::assertCount(1, array_unique(array_column($rows, 'time')));
    }

    public function testAdminReturnLinksTheRidersStartAndRepeatedForceReturnStaysUnpaired(): void
    {
        $this->client->request('POST', '/api/v1/rentals', ['bikeNumber' => self::BIKE_NUMBER]);
        self::assertResponseIsSuccessful();
        $start = $this->history()[0];
        $adminId = $this->login(self::ADMIN_PHONE_NUMBER);
        $this->returnBike(true);
        $this->returnBike(true);
        $rows = $this->history();
        self::assertSame($start, $rows[0]);
        self::assertSame(['RENT', 'FORCERETURN', 'FORCERETURN'], array_column($rows, 'action'));
        self::assertSame([null, $start['id'], null], array_column($rows, 'pairActionId'));
        self::assertSame($this->userId, $rows[0]['userId']);
        self::assertSame($adminId, $rows[1]['userId']);
    }

    public function testOrdinaryReturnCanCloseAForcedRent(): void
    {
        $this->login(self::ADMIN_PHONE_NUMBER);
        $this->client->request('POST', '/api/v1/admin/rentals/force', ['bikeNumber' => self::BIKE_NUMBER]);
        self::assertResponseIsSuccessful();
        $this->returnBike();
        $rows = $this->history();
        self::assertSame(['FORCERENT', 'RETURN'], array_column($rows, 'action'));
        self::assertSame([null, $rows[0]['id']], array_column($rows, 'pairActionId'));
    }

    public function testForceReturnOfParkedBikeDoesNotInventAStart(): void
    {
        $this->login(self::ADMIN_PHONE_NUMBER);
        $this->returnBike(true);
        $rows = $this->history();
        self::assertSame(['FORCERETURN'], array_column($rows, 'action'));
        self::assertNull($rows[0]['pairActionId']);
    }

    private function returnBike(bool $force = false): void
    {
        $this->client->request('POST', $force ? '/api/v1/admin/returns/force' : '/api/v1/returns', [
            'bikeNumber' => self::BIKE_NUMBER, 'standName' => self::STAND_NAME,
        ]);
        self::assertResponseIsSuccessful();
    }

    private function login(string $phone): int
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier($phone);
        $this->client->loginUser($user);

        return $user->getUserId();
    }

    private function history(): array
    {
        return $this->db->query(
            'SELECT * FROM history WHERE bikeNum = :bikeNum AND id > :initialHistoryId ORDER BY id',
            ['bikeNum' => self::BIKE_NUMBER, 'initialHistoryId' => $this->initialHistoryId],
        )->fetchAllAssoc();
    }
}
