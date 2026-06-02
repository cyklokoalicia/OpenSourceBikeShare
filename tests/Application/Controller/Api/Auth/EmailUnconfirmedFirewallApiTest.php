<?php

/**
 * Application tests for per-request email-confirmation enforcement on the api_v1 firewall.
 *
 * An account with a pending email confirmation (a `registration` token row) must be
 * rejected with 403 `email_unconfirmed` on Bearer-protected requests, even if a token
 * was somehow issued; once the email is confirmed the same token passes the check.
 */

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Auth;

use BikeShare\App\Security\JwtTokenService;
use BikeShare\App\Security\UserProvider;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class EmailUnconfirmedFirewallApiTest extends BikeSharingWebTestCase
{
    public function testBearerRequestIsRejectedWhileEmailUnconfirmedThenPassesAfterConfirmation(): void
    {
        ['email' => $userEmail, 'phone' => $userPhone, 'password' => $password]
            = $this->registerUnconfirmedUser('903');

        $container = $this->client->getContainer();
        $phonePurifier = $container->get(PhonePurifierInterface::class);
        $purifiedPhone = $phonePurifier->purify($userPhone);

        // Token issuance is blocked while email is unconfirmed, so forge an access token
        // directly to exercise the per-request firewall check on a Bearer endpoint.
        $userProvider = $container->get(UserProvider::class);
        $jwtTokenService = $container->get(JwtTokenService::class);
        $registrationRepository = $container->get(RegistrationRepository::class);
        $userRepository = $container->get(UserRepository::class);

        $userRow = $userRepository->findItemByEmail($userEmail);
        $this->assertNotNull($userRow);
        $this->assertNotNull(
            $registrationRepository->findItemByUserId($userRow['userId']),
            'Freshly registered user must have a pending registration token'
        );

        $user = $userProvider->loadUserByIdentifier($purifiedPhone);
        $accessToken = $jwtTokenService->createAccessToken($user)['token'];

        // /user/phone-confirm/request is reachable by ROLE_NEWBIE, so a 403 here isolates
        // the email UserChecker rather than the phone access-control gate.
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/request',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken]
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $problem = $this->decodeJsonResponse();
        $this->assertSame('email_unconfirmed', $problem['code'] ?? null);
        $this->assertStringContainsString('email', strtolower((string)($problem['detail'] ?? '')));

        // Confirm the email out-of-band via the link from the registration mail.
        $mailSender = $container->get(MailSenderInterface::class);
        $body = $mailSender->getSentMessages()[0]['message'] ?? '';
        preg_match('/(\/user\/confirm\/email\/[a-z0-9]+)/', (string)$body, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Email confirmation link not found');
        $this->client->request(Request::METHOD_GET, $matches[1]);
        $this->assertResponseRedirects();
        $this->assertNull($registrationRepository->findItemByUserId($userRow['userId']));

        // Same token now passes the email check (phone-confirm allowed for ROLE_NEWBIE).
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/user/phone-confirm/request',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $accessToken]
        );
        $this->assertResponseIsSuccessful();
    }

    public function testTokenIssuanceStillRejectsEmailUnconfirmed(): void
    {
        ['phone' => $userPhone, 'password' => $password] = $this->registerUnconfirmedUser('904');

        $phonePurifier = $this->client->getContainer()->get(PhonePurifierInterface::class);

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/token',
            ['number' => $phonePurifier->purify($userPhone), 'password' => $password]
        );
        $this->assertResponseStatusCodeSame(403);
        $problem = $this->decodeJsonResponse();
        $this->assertSame('email_unconfirmed', $problem['code'] ?? null);
        $this->assertStringContainsString('email', strtolower((string)($problem['detail'] ?? '')));
    }

    /**
     * Register a fresh user (email left unconfirmed) and return its credentials.
     *
     * @return array{email: string, phone: string, password: string}
     */
    private function registerUnconfirmedUser(string $phonePrefix): array
    {
        $userEmail = 'email_fw_' . time() . '_' . bin2hex(random_bytes(4)) . '@example.com';
        $userPhone = '+421' . $phonePrefix . rand(100000, 999999);
        $password = 'password123';

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'fullname' => 'Eve Doe',
                'city' => 'Default City',
                'useremail' => $userEmail,
                'password' => $password,
                'password2' => $password,
                'number' => $userPhone,
                'agree' => true,
            ])
        );
        $this->assertResponseStatusCodeSame(201);

        return ['email' => $userEmail, 'phone' => $userPhone, 'password' => $password];
    }
}
