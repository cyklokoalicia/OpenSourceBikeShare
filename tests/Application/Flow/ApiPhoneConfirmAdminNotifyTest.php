<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class ApiPhoneConfirmAdminNotifyTest extends BikeSharingWebTestCase
{
    private const USER_ID = 10;
    private const USER_PHONE_NUMBER = '421951555555';
    private const USER_PASSWORD = 'password';

    protected function setUp(): void
    {
        parent::setUp();
        // Mark phone as unconfirmed so we exercise the phone-confirm flow.
        // Also reset the password — UserControllerTest::testChangePassword may have changed it
        // depending on test discovery order.
        $db = $this->client->getContainer()->get(DbInterface::class);
        $db->query(
            'UPDATE users SET isNumberConfirmed = 0, password = :password WHERE userId = :userId',
            [
                'userId' => self::USER_ID,
                'password' => password_hash(self::USER_PASSWORD, PASSWORD_BCRYPT, ['cost' => 13]),
            ]
        );
    }

    protected function tearDown(): void
    {
        // Restore the fixture state so other tests aren't affected.
        $db = $this->client->getContainer()->get(DbInterface::class);
        $db->query(
            'UPDATE users SET isNumberConfirmed = 1 WHERE userId = :userId',
            ['userId' => self::USER_ID]
        );
        parent::tearDown();
    }

    public function testAdminNotifiedAfterApiPhoneConfirmVerify(): void
    {
        // 1. Get JWT access token.
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/token',
            [
                'number' => self::USER_PHONE_NUMBER,
                'password' => self::USER_PASSWORD,
            ]
        );
        $this->assertResponseIsSuccessful();
        $tokenPayload = $this->decodeApiResponseData();
        $this->assertFalse($tokenPayload['phoneConfirmed'], 'Phone should be unconfirmed at this point');
        $authHeader = ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenPayload['accessToken']];

        // 2. Request SMS code via API.
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/request',
            server: $authHeader
        );
        $this->assertResponseIsSuccessful();
        $requestPayload = $this->decodeApiResponseData();
        $this->assertArrayHasKey('checkCode', $requestPayload);
        $checkCode = $requestPayload['checkCode'];

        // 3. Pull the SMS code from DebugSmsSender.
        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $sentMessages = $smsSender->getSentMessages();
        $this->assertCount(1, $sentMessages, 'Expected one SMS with the verification code');
        $smsMessage = $sentMessages[0]['message'];
        $this->assertInstanceOf(TranslatableMessage::class, $smsMessage);
        $smsCodeRaw = $smsMessage->getParameters()['smsCode'] ?? '';
        $this->assertMatchesRegularExpression('/^[A-Z]{2} \d+$/', $smsCodeRaw);

        // 4. Verify the SMS code via API — this dispatches UserVerificationCompletedEvent.
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/verify',
            [
                'code' => $smsCodeRaw,
                'checkCode' => $checkCode,
            ],
            server: $authHeader
        );
        $this->assertResponseIsSuccessful();

        // Capture admin emails BEFORE any subsequent request (DebugMailSender resets per request).
        $emailsAfterVerify = static::getContainer()->get(MailSenderInterface::class)->getSentMessages();

        $this->assertCount(1, $emailsAfterVerify, 'Expected exactly one admin notification email after API phone confirmation');

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findItem(self::USER_ID);
        $this->assertSame(1, (int)$user['isNumberConfirmed'], 'Phone should be confirmed after verify');

        // Fixture defines superAdmin (userId=7, privileges=7) — only user matching `privileges & 2 != 0`.
        $superAdmin = $userRepository->findItem(7);
        $adminEmail = $emailsAfterVerify[0];
        $this->assertSame($superAdmin['mail'], $adminEmail['recipient']);
        $this->assertStringContainsString($user['mail'], $adminEmail['message']);
        $this->assertStringContainsString($user['number'], $adminEmail['message']);
    }
}
