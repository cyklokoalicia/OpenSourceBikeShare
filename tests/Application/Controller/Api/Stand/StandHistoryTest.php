<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Spec 0013: admin stand ride history — GET /api/v1/admin/stands/{standId}/history.
 * Returns both rentals from and returns to the stand (standId is populated on each).
 */
class StandHistoryTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const USER_PHONE_NUMBER = '421951111111';
    private const STAND_ID = 1;
    // A bike number absent from fixtures, so the seeded history is isolated and cheap to clean up.
    private const SENTINEL_BIKE = 9999;

    protected function tearDown(): void
    {
        $this->client->getContainer()->get(DbInterface::class)
            ->query('DELETE FROM history WHERE bikeNum = :bike', ['bike' => self::SENTINEL_BIKE]);

        parent::tearDown();
    }

    private function loginAdmin(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);
    }

    public function testForbiddenForRegularUser(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/' . self::STAND_ID . '/history');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testReturns404ForUnknownStand(): void
    {
        $this->loginAdmin();

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/999999/history');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReturnsHistoryListForKnownStand(): void
    {
        $this->loginAdmin();

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/' . self::STAND_ID . '/history');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($this->decodeApiResponseData());
    }

    public function testListsBothRentalsFromAndReturnsToTheStand(): void
    {
        $db = $this->client->getContainer()->get(DbInterface::class);
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $userId = $user->getUserId();

        // A return TO this stand, then a rent FROM it — both now carry standId (spec 0013),
        // so both must appear in the stand's history. Timestamps are set near the top of the
        // MySQL TIMESTAMP range (max 2038-01-19) so these rows stay newest — and thus inside
        // the newest-first, limited list — regardless of other tests' (real-now) data.
        $db->query(
            "INSERT INTO history (userId, bikeNum, action, parameter, standId, time)
             VALUES (:u, :b, 'RETURN', :stand, :standId, '2037-12-31 10:00:00')",
            ['u' => $userId, 'b' => self::SENTINEL_BIKE, 'stand' => (string)self::STAND_ID, 'standId' => self::STAND_ID]
        );
        $db->query(
            "INSERT INTO history (userId, bikeNum, action, parameter, standId, time)
             VALUES (:u, :b, 'RENT', 'TESTCODE', :standId, '2037-12-31 11:00:00')",
            ['u' => $userId, 'b' => self::SENTINEL_BIKE, 'standId' => self::STAND_ID]
        );

        $this->loginAdmin();
        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/' . self::STAND_ID . '/history');

        $this->assertResponseIsSuccessful();
        $events = $this->decodeApiResponseData();
        $mine = array_values(array_filter($events, fn($r) => (int)$r['bikeNumber'] === self::SENTINEL_BIKE));

        $this->assertCount(2, $mine, 'Both the rent from and the return to the stand should be listed');
        $actions = array_column($mine, 'action');
        $this->assertContains('RENT', $actions);
        $this->assertContains('RETURN', $actions);
        // Newest-first ordering: the RENT (11:00) precedes the RETURN (10:00).
        $this->assertSame('RENT', $mine[0]['action']);
        $this->assertSame($userId, (int)$mine[0]['userId']);
        $this->assertArrayHasKey('userName', $mine[0]);
        $this->assertArrayHasKey('time', $mine[0]);
    }
}
