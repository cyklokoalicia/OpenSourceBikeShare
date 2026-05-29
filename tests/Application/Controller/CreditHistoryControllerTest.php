<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Enum\Action;
use BikeShare\Enum\CreditChangeType;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class CreditHistoryControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    protected function setUp(): void
    {
        $this->setEnvVar('CREDIT_SYSTEM_ENABLED', '1');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Clean up credit history for user
        $user = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $db = self::getContainer()->get(DbInterface::class);
        $db->query(
            'DELETE FROM history WHERE userId = :userId AND action IN (:creditAction, :creditChangeAction)',
            [
                'userId' => $user['userId'],
                'creditAction' => Action::CREDIT->value,
                'creditChangeAction' => Action::CREDIT_CHANGE->value,
            ]
        );
        $db->query(
            'DELETE FROM credit WHERE userId = :userId',
            [
                'userId' => $user['userId'],
            ]
        );

        parent::tearDown();
    }

    public function testCreditHistoryPageLoads(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request('GET', '/credit/history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Credit History');
    }

    public function testCreditHistoryShowsCurrentBalance(): void
    {
        $userData = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $creditSystem = self::getContainer()->get(CreditSystemInterface::class);
        $creditSystem->increaseCredit($userData['userId'], 50.0, CreditChangeType::CREDIT_ADD);

        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request('GET', '/credit/history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card-text', '50');
    }

    public function testCreditHistoryDisplaysTransactions(): void
    {
        $userData = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $creditSystem = self::getContainer()->get(CreditSystemInterface::class);
        $creditSystem->increaseCredit($userData['userId'], 25.0, CreditChangeType::CREDIT_ADD);
        $creditSystem->increaseCredit($userData['userId'], 10.0, CreditChangeType::CREDIT_ADD);

        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/credit/history');

        $this->assertResponseIsSuccessful();
        $this->assertEquals(2, $crawler->filter('table tbody tr')->count());
    }

    public function testCreditHistoryRequiresAuthentication(): void
    {
        $this->client->request('GET', '/credit/history');

        $this->assertResponseRedirects('/login');
    }

    public function testCreditHistoryNotFoundWhenCreditSystemDisabled(): void
    {
        $this->setEnvVar('CREDIT_SYSTEM_ENABLED', '0');

        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request('GET', '/credit/history');

        $this->assertResponseStatusCodeSame(404);
    }
}
