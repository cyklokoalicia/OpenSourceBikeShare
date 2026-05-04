<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class StandUpdateStatusTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const USER_PHONE_NUMBER = '421951111111';
    private const STAND_ID = 1;

    protected function tearDown(): void
    {
        $db = $this->client->getContainer()->get(DbInterface::class);
        $db->query(
            'UPDATE stands SET status = :status WHERE standId = :standId',
            ['status' => 'active', 'standId' => self::STAND_ID]
        );

        parent::tearDown();
    }

    public function testUpdateStatusToTechnical(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $this->client->request(
            Request::METHOD_PATCH,
            '/api/v1/admin/stands/' . self::STAND_ID,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['status' => 'technical'])
        );

        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertSame('technical', $response['status']);
        $this->assertArrayHasKey('message', $response);

        $db = $this->client->getContainer()->get(DbInterface::class);
        $row = $db->query(
            'SELECT status FROM stands WHERE standId = :standId',
            ['standId' => self::STAND_ID]
        )->fetchAssoc();
        $this->assertSame('technical', $row['status']);

        $received = $db->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/v1/admin/stands/' . self::STAND_ID,
            $received['sms_text'],
            'PATCH request was not logged in received table'
        );

        $sent = $db->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertStringContainsString('active -> technical', $sent['text'], 'Sent log missing status transition');
        $this->assertStringContainsString('STAND1', $sent['text'], 'Sent log missing stand name');
    }

    public function testUpdateStatusAcceptsAllValidValues(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        foreach (['technical', 'hidden', 'inactive', 'active'] as $status) {
            $this->client->request(
                Request::METHOD_PATCH,
                '/api/v1/admin/stands/' . self::STAND_ID,
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode(['status' => $status])
            );
            $this->assertResponseIsSuccessful();
            $response = $this->decodeApiResponseData();
            $this->assertSame($status, $response['status']);
        }
    }

    public function testUpdateStatusRejectsInvalidStatus(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $this->client->request(
            Request::METHOD_PATCH,
            '/api/v1/admin/stands/' . self::STAND_ID,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['status' => 'banana'])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateStatusReturns404ForUnknownStand(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $this->client->request(
            Request::METHOD_PATCH,
            '/api/v1/admin/stands/9999',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['status' => 'active'])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateStatusForbiddenForRegularUser(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(
            Request::METHOD_PATCH,
            '/api/v1/admin/stands/' . self::STAND_ID,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['status' => 'inactive'])
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetStandById(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/' . self::STAND_ID);

        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertSame(self::STAND_ID, $response['standId']);
        $this->assertArrayHasKey('standName', $response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetStandByIdReturns404ForUnknownId(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands/9999');

        $this->assertResponseStatusCodeSame(404);
    }
}
