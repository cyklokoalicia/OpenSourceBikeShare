<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api;

use BikeShare\App\Security\UserProvider;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Repository\CouponRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class CouponApiControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';
    private const ADMIN_PHONE_NUMBER = '421222222222';

    private $creditSystemEnabled;

    protected function setup(): void
    {
        $this->creditSystemEnabled = $_ENV['CREDIT_SYSTEM_ENABLED'];
        parent::setup();
    }

    protected function tearDown(): void
    {
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);
        $userCredit = $creditSystem->getUserCredit($user['userId']);
        if ($userCredit > 0) {
            $creditSystem->useCredit($user['userId'], $userCredit);
        }

        $_ENV['CREDIT_SYSTEM_ENABLED'] = $this->creditSystemEnabled;
        parent::tearDown();
    }

    public function testCouponUse(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = '1';

        #generate coupons
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);
        $this->client->request('POST', '/api/coupon/generate', ['multiplier' => 1]);
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertJson($response->getContent());
        $response = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $response, 'Response does not contain error key');
        $this->assertSame(0, $response['error'], 'Response with error: ' . $response['message']);

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/coupon/generate',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertMatchesRegularExpression(
            '/Generated \d* new \d*.* coupons\./',
            $sent['text'],
            'Send message is not logged'
        );


        #get coupons
        $this->client->request('GET', '/api/coupon');
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertJson($response->getContent());
        $response = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $coupon = $response[0]['coupon'] ?? null;
        $this->assertNotNull($coupon, 'Coupon not found in response');

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/coupon',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertMatchesRegularExpression(
            '/Generated \d* new \d*.* coupons\./',
            $sent['text'],
            'Send message is not logged'
        );

        #use coupon by user
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request('POST', '/api/coupon/use', ['coupon' => $coupon]);
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertJson($response->getContent());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $data, 'Response does not contain message key');
        $this->assertArrayHasKey('error', $data, 'Response does not contain error key');
        $this->assertSame(0, $data['error'], 'Response with error: ' . $data['message']);
        $this->assertStringContainsString('Coupon ' . $coupon . ' has been redeemed.', $data['message']);

        $couponData = $this->client->getContainer()->get(CouponRepository::class)->findActiveItem($coupon);
        $this->assertNull($couponData, 'Coupon is not used');

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/coupon/use',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertStringContainsString(
            'Coupon ' . $coupon . ' has been redeemed.',
            $sent['text'],
            'Send message is not logged'
        );
    }
}
