<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserClientRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;

class ClientVersionTrackingTest extends BikeSharingWebTestCase
{
    use ClockSensitiveTrait;

    // userForRegistrationTest — picked because no other test inspects `received` for this number.
    private const USER_PHONE_NUMBER = '421951333333';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const ANDROID_USER_AGENT = 'OpenSourceBikeShare-Android/1.2.3 (45)';

    protected function tearDown(): void
    {
        $this->client->getContainer()->get(DbInterface::class)->query(
            'DELETE uc FROM userClient uc JOIN users u ON uc.userId = u.userId WHERE u.number = :number',
            ['number' => self::USER_PHONE_NUMBER]
        );
        parent::tearDown();
    }

    public function testAndroidUserAgentRecordsClient(): void
    {
        $user = $this->loginAs(self::USER_PHONE_NUMBER);

        $this->callTrackedEndpoint(self::ANDROID_USER_AGENT);

        $clients = $this->fetchUserClients($user->getUserId());
        $this->assertCount(1, $clients);
        $this->assertSame('android', $clients[0]['platform']);
        $this->assertSame('1.2.3', $clients[0]['version']);
        $this->assertNotEmpty($clients[0]['lastSeenAt']);
    }

    #[DataProvider('nonAndroidUserAgentProvider')]
    public function testNonAndroidUserAgentDoesNotRecordClient(string $userAgent): void
    {
        $user = $this->loginAs(self::USER_PHONE_NUMBER);

        $this->callTrackedEndpoint($userAgent);

        $this->assertSame([], $this->fetchUserClients($user->getUserId()));
    }

    public static function nonAndroidUserAgentProvider(): array
    {
        return [
            'browser' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36'],
            'legacy okhttp' => ['okhttp/4.12.0'],
        ];
    }

    public function testRapidRepeatedSameVersionCallsAreThrottled(): void
    {
        static::mockTime('2026-05-09 10:00:00');
        $user = $this->loginAs(self::USER_PHONE_NUMBER);

        $this->callTrackedEndpoint(self::ANDROID_USER_AGENT);
        $first = $this->fetchUserClients($user->getUserId());
        $this->assertSame('2026-05-09 10:00:00', $first[0]['lastSeenAt']);

        static::mockTime('2026-05-09 10:30:00');
        $this->callTrackedEndpoint(self::ANDROID_USER_AGENT);
        $second = $this->fetchUserClients($user->getUserId());

        $this->assertSame('2026-05-09 10:00:00', $second[0]['lastSeenAt']);
    }

    public function testStaleTimestampGetsRefreshedAfterThrottleWindow(): void
    {
        static::mockTime('2026-05-09 10:00:00');
        $user = $this->loginAs(self::USER_PHONE_NUMBER);
        $this->callTrackedEndpoint(self::ANDROID_USER_AGENT);

        static::mockTime('2026-05-09 11:30:00');
        $this->callTrackedEndpoint(self::ANDROID_USER_AGENT);

        $clients = $this->fetchUserClients($user->getUserId());
        $this->assertSame('2026-05-09 11:30:00', $clients[0]['lastSeenAt']);
        $this->assertSame('1.2.3', $clients[0]['version']);
    }

    public function testVersionChangeUpdatesImmediatelyWithinThrottleWindow(): void
    {
        static::mockTime('2026-05-09 10:00:00');
        $user = $this->loginAs(self::USER_PHONE_NUMBER);
        $this->callTrackedEndpoint(self::ANDROID_USER_AGENT);

        static::mockTime('2026-05-09 10:05:00');
        $this->callTrackedEndpoint('OpenSourceBikeShare-Android/1.3.0 (50)');

        $clients = $this->fetchUserClients($user->getUserId());
        $this->assertSame('1.3.0', $clients[0]['version']);
        $this->assertSame('2026-05-09 10:05:00', $clients[0]['lastSeenAt']);
    }

    public function testHighFrequencyMarkersRouteIsSkipped(): void
    {
        $user = $this->loginAs(self::USER_PHONE_NUMBER);

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/stands/markers',
            server: ['HTTP_USER_AGENT' => self::ANDROID_USER_AGENT]
        );
        $this->assertResponseIsSuccessful();

        $this->assertSame([], $this->fetchUserClients($user->getUserId()));
    }

    public function testAdminEndpointReturnsClientList(): void
    {
        $user = $this->loginAs(self::USER_PHONE_NUMBER);
        $this->client->getContainer()->get(UserClientRepository::class)
            ->recordSeen($user->getUserId(), 'android', '1.0.5');

        $this->loginAs(self::ADMIN_PHONE_NUMBER);
        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/admin/users/' . $user->getUserId(),
        );
        $this->assertResponseIsSuccessful();
        $payload = $this->decodeApiResponseData();

        $this->assertArrayHasKey('clients', $payload);
        $this->assertCount(1, $payload['clients']);
        $this->assertSame('android', $payload['clients'][0]['platform']);
        $this->assertSame('1.0.5', $payload['clients'][0]['version']);
    }

    private function loginAs(string $phoneNumber): User
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier($phoneNumber);
        $this->client->loginUser($user);

        return $user;
    }

    private function callTrackedEndpoint(string $userAgent): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/stands/STAND1/bikes',
            server: ['HTTP_USER_AGENT' => $userAgent]
        );
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return list<array{platform: string, version: string, lastSeenAt: string}>
     */
    private function fetchUserClients(int $userId): array
    {
        return $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT platform, version, lastSeenAt FROM userClient
              WHERE userId = :userId
              ORDER BY lastSeenAt DESC',
            ['userId' => $userId]
        )->fetchAllAssoc();
    }
}
