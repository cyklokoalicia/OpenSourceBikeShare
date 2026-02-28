<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Auth;

use BikeShare\Db\DbInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class AuthFlowTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951555555';
    private const USER_PASSWORD = 'password';

    public function testTokenRefreshAndAccessFlow(): void
    {
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

        $this->assertArrayHasKey('accessToken', $tokenPayload);
        $this->assertArrayHasKey('refreshToken', $tokenPayload);
        $this->assertArrayHasKey('tokenType', $tokenPayload);
        $this->assertSame('Bearer', $tokenPayload['tokenType']);
        $this->assertSame('v1', $this->getTokenHeaderField($tokenPayload['accessToken'], 'kid'));

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/me/limits',
            server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenPayload['accessToken']]
        );
        $this->assertResponseIsSuccessful();
        $limitsPayload = $this->decodeApiResponseData();
        $this->assertArrayHasKey('limit', $limitsPayload);
        $this->assertArrayHasKey('rented', $limitsPayload);
        $this->assertArrayHasKey('userCredit', $limitsPayload);

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/refresh',
            ['refreshToken' => $tokenPayload['refreshToken']]
        );
        $this->assertResponseIsSuccessful();
        $refreshedPayload = $this->decodeApiResponseData();

        $this->assertArrayHasKey('accessToken', $refreshedPayload);
        $this->assertArrayHasKey('refreshToken', $refreshedPayload);
        $this->assertNotSame($tokenPayload['accessToken'], $refreshedPayload['accessToken']);
        $this->assertNotSame($tokenPayload['refreshToken'], $refreshedPayload['refreshToken']);
    }

    public function testInvalidCredentialsReturnProblemResponse(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/auth/token',
            [
                'number' => self::USER_PHONE_NUMBER,
                'password' => 'invalid-password',
            ]
        );

        $this->assertResponseStatusCodeSame(401);
        $payload = $this->decodeJsonResponse();
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('status', $payload);
        $this->assertArrayHasKey('detail', $payload);
        $this->assertArrayNotHasKey('message', $payload);
        $this->assertSame(401, $payload['status']);
    }

    public function testMissingAuthorizationReturnsBearerChallengeHeader(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/v1/me/limits');

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame('Bearer', $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testUnconfirmedUserCannotGetToken(): void
    {
        $db = $this->client->getContainer()->get(DbInterface::class);
        $userId = 10;
        $db->query('DELETE FROM registration WHERE userId = :userId', ['userId' => $userId]);
        $db->query(
            'INSERT INTO registration (userId, userKey) VALUES (:userId, :userKey)',
            ['userId' => $userId, 'userKey' => 'pending-confirmation']
        );

        try {
            $this->client->request(
                Request::METHOD_POST,
                '/api/v1/auth/token',
                [
                    'number' => self::USER_PHONE_NUMBER,
                    'password' => self::USER_PASSWORD,
                ]
            );

            $this->assertResponseStatusCodeSame(403);
            $payload = $this->decodeJsonResponse();
            $this->assertArrayHasKey('detail', $payload);
            $this->assertNotEmpty($payload['detail']);
            $this->assertArrayNotHasKey('accessToken', $payload);
        } finally {
            $db->query('DELETE FROM registration WHERE userId = :userId', ['userId' => $userId]);
        }
    }

    private function getTokenHeaderField(string $token, string $field): mixed
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $encodedHeader = strtr($parts[0], '-_', '+/');
        $paddedHeader = str_pad($encodedHeader, (int)ceil(strlen($encodedHeader) / 4) * 4, '=', STR_PAD_RIGHT);
        $decodedHeader = base64_decode($paddedHeader, true);
        $this->assertNotFalse($decodedHeader);

        $header = json_decode($decodedHeader, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($header);

        return $header[$field] ?? null;
    }
}
