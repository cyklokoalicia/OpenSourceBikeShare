<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class ApiPhoneConfirmAdminNotifyTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951666666';
    private const SUPER_ADMIN_PHONE_NUMBER = '421951777777';
    private const USER_PASSWORD = 'password';

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

        $this->assertCount(
            1,
            $emailsAfterVerify,
            'Expected exactly one admin notification email after API phone confirmation'
        );

        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $this->assertSame(1, (int)$user['isNumberConfirmed'], 'Phone should be confirmed after verify');

        $superAdmin = $userRepository->findItemByPhoneNumber(self::SUPER_ADMIN_PHONE_NUMBER);
        $adminEmail = $emailsAfterVerify[0];
        $this->assertSame($superAdmin['mail'], $adminEmail['recipient']);
        $this->assertStringContainsString($user['mail'], $adminEmail['message']);
        $this->assertStringContainsString($user['number'], $adminEmail['message']);
    }
}
