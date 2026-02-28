<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\User;

use BikeShare\App\Security\UserProvider;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Enum\CreditChangeType;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class UserCreditHistoryTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $_ENV['CREDIT_SYSTEM_ENABLED'] = '1';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    public function testCreditHistoryReturnsArray(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/v1/me/credit-history');
        $this->assertResponseIsSuccessful();
        $data = $this->decodeApiResponseData();
        $this->assertIsArray($data, 'Response data must be an array');
    }

    public function testCreditHistoryReturnsItemsWithExpectedKeys(): void
    {
        $userData = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);
        $creditSystem->increaseCredit($userData['userId'], 25.0, CreditChangeType::CREDIT_ADD);

        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/api/v1/me/credit-history');
        $this->assertResponseIsSuccessful();
        $data = $this->decodeApiResponseData();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data, 'Credit history should contain at least one entry after adding credit');
        $first = $data[0];
        $this->assertArrayHasKey('date', $first);
        $this->assertArrayHasKey('amount', $first);
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('balance', $first);
    }

    public function testCreditHistoryRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/v1/me/credit-history');
        $this->assertResponseStatusCodeSame(401);
    }
}
