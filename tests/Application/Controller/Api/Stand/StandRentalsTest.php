<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Spec 0009: admin stand ride history — GET /api/v1/admin/stands/{standId}/rentals.
 */
class StandRentalsTest extends BikeSharingWebTestCase
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

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/' . self::STAND_ID . '/rentals');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testReturns404ForUnknownStand(): void
    {
        $this->loginAdmin();

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/9999/rentals');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReturnsRentalListForKnownStand(): void
    {
        $this->loginAdmin();

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/' . self::STAND_ID . '/rentals');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($this->decodeApiResponseData());
    }

    public function testListsRentalsOriginatingAtTheStand(): void
    {
        $db = $this->client->getContainer()->get(DbInterface::class);
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $userId = $user->getUserId();

        // The bike was last returned TO this stand, then rented — so the rent originates here.
        // Spec 0013: the RENT row carries its origin stand directly in standId.
        $db->query(
            "INSERT INTO history (userId, bikeNum, action, parameter, standId, time)
             VALUES (:u, :b, 'RETURN', :stand, :standId, '2026-05-01 10:00:00')",
            ['u' => $userId, 'b' => self::SENTINEL_BIKE, 'stand' => (string)self::STAND_ID, 'standId' => self::STAND_ID]
        );
        $db->query(
            "INSERT INTO history (userId, bikeNum, action, parameter, standId, time)
             VALUES (:u, :b, 'RENT', 'TESTCODE', :standId, '2026-05-01 11:00:00')",
            ['u' => $userId, 'b' => self::SENTINEL_BIKE, 'standId' => self::STAND_ID]
        );

        $this->loginAdmin();
        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/' . self::STAND_ID . '/rentals');

        $this->assertResponseIsSuccessful();
        $rentals = $this->decodeApiResponseData();
        $match = array_values(array_filter($rentals, fn($r) => (int)$r['bikeNumber'] === self::SENTINEL_BIKE));
        $this->assertCount(1, $match, 'Seeded rental from the stand should be listed');
        $this->assertSame($userId, (int)$match[0]['userId']);
        $this->assertArrayHasKey('userName', $match[0]);
        $this->assertArrayHasKey('rentTime', $match[0]);
    }
}
