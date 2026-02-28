<?php

/**
 * Application tests for phone confirmation API endpoints.
 * Full flow test registers a user, confirms email, then confirms phone via API.
 */

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Auth;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class PhoneConfirmApiTest extends BikeSharingWebTestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    public function testPhoneConfirmRequestRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/v1/user/phone-confirm/request');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testPhoneConfirmVerifyRequiresAuthentication(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/verify',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['code' => 'AB123456', 'checkCode' => 'some-check-code'])
        );
        $this->assertResponseStatusCodeSame(401);
    }

    public function testFullRegistrationAndPhoneConfirmFlow(): void
    {
        $userEmail = 'test_' . time() . '_' . bin2hex(random_bytes(4)) . '@example.com';
        $userPhone = '+421901' . rand(100000, 999999);
        $password = 'password123';
        $city = 'Default City';

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullname' => 'John Doe',
                'city' => $city,
                'useremail' => $userEmail,
                'password' => $password,
                'password2' => $password,
                'number' => $userPhone,
                'agree' => true,
            ])
        );
        $this->assertResponseStatusCodeSame(201);

        $mailSender = $this->client->getContainer()->get(MailSenderInterface::class);
        $sendEmails = $mailSender->getSentMessages();
        $this->assertCount(1, $sendEmails);
        $body = $sendEmails[0]['message'] ?? '';
        $this->assertNotNull($body);
        preg_match('/(\/user\/confirm\/email\/[a-z0-9]+)/', $body, $matches);
        $this->assertNotEmpty($matches[1], 'Email confirmation link not found');
        $confirmationLink = $matches[1];

        $this->client->request(Request::METHOD_GET, $confirmationLink);
        $this->assertResponseRedirects();

        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        $registrationRepository = $this->client->getContainer()->get(RegistrationRepository::class);
        $user = $userRepository->findItemByEmail($userEmail);
        $this->assertNotNull($user);
        $this->assertNull($registrationRepository->findItemByUserId($user['userId']));

        $phonePurifier = $this->client->getContainer()->get(PhonePurifierInterface::class);
        $purifiedPhone = $phonePurifier->purify($userPhone);

        $tokenPayload = $this->obtainToken($purifiedPhone, $password);
        $this->assertArrayHasKey('accessToken', $tokenPayload);
        $this->assertFalse($tokenPayload['phoneConfirmed'], 'New user should have phone not confirmed');

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/request',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenPayload['accessToken']]
        );
        $this->assertResponseIsSuccessful();
        $requestData = $this->decodeApiResponseData();
        $this->assertArrayHasKey('checkCode', $requestData);
        $checkCode = $requestData['checkCode'];

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount(1, $smsConnector->getSentMessages());
        $sent = $smsConnector->getSentMessages()[0];
        $this->assertSame($purifiedPhone, $sent['number']);
        preg_match('/Enter this code to verify your phone: ([A-Z]{2} \d+)/', $sent['text'], $smsMatches);
        $this->assertNotEmpty($smsMatches[1] ?? null);
        $smsCode = str_replace(' ', '', $smsMatches[1]);

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/verify',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenPayload['accessToken'],
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['code' => $smsCode, 'checkCode' => $checkCode])
        );
        $this->assertResponseIsSuccessful();
        $verifyData = $this->decodeApiResponseData();
        $this->assertArrayHasKey('message', $verifyData);
        $this->assertStringContainsString('confirmed', $verifyData['message']);

        $user = $userRepository->findItemByEmail($userEmail);
        $this->assertSame(1, (int)$user['isNumberConfirmed']);

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/request',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenPayload['accessToken']]
        );
        $this->assertResponseIsSuccessful();
        $data = $this->decodeApiResponseData();
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('already confirmed', $data['message']);
    }

    public function testPhoneConfirmVerifyRejectsInvalidCode(): void
    {
        $userEmail = 'test_invalid_' . time() . '_' . bin2hex(random_bytes(4)) . '@example.com';
        $userPhone = '+421902' . rand(100000, 999999);
        $password = 'password123';

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullname' => 'Jane Doe',
                'city' => 'Default City',
                'useremail' => $userEmail,
                'password' => $password,
                'password2' => $password,
                'number' => $userPhone,
                'agree' => true,
            ])
        );
        $this->assertResponseStatusCodeSame(201);

        $mailSender = $this->client->getContainer()->get(MailSenderInterface::class);
        preg_match('/(\/user\/confirm\/email\/[a-z0-9]+)/', $mailSender->getSentMessages()[0]['message'], $m);
        $this->client->request(Request::METHOD_GET, $m[1]);

        $phonePurifier = $this->client->getContainer()->get(PhonePurifierInterface::class);
        $tokenPayload = $this->obtainToken($phonePurifier->purify($userPhone), $password);

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/request',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenPayload['accessToken']]
        );
        $this->assertResponseIsSuccessful();
        $requestData = $this->decodeApiResponseData();
        $checkCode = $requestData['checkCode'];

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/verify',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenPayload['accessToken'],
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['code' => 'WRONG00', 'checkCode' => $checkCode])
        );
        $this->assertResponseStatusCodeSame(400);
        $payload = $this->decodeJsonResponse();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertStringContainsString('Invalid', $payload['detail']);
    }

    private function obtainToken(string $number, string $password): array
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/token',
            ['number' => $number, 'password' => $password]
        );
        $this->assertResponseIsSuccessful();

        return $this->decodeApiResponseData();
    }
}
