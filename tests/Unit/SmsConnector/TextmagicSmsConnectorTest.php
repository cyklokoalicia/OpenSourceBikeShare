<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsConnector;

use BikeShare\SmsConnector\TextmagicSmsConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class TextmagicSmsConnectorTest extends TestCase
{
    private const CONFIG = [
        'textmagic' => [
            'gatewayUser' => 'u',
            'gatewayPassword' => 's3cr3t',
            'gatewaySenderNumber' => '421900000000',
        ],
    ];

    public function testSendRequestsTheGateway(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url) use (&$requests): MockResponse {
                $requests[] = [$method, $url];

                return new MockResponse('', ['http_code' => 200]);
            }
        );

        $this->connector($httpClient)->send('421951111111', 'Hello world');

        $this->assertCount(1, $requests);
        [$method, $url] = $requests[0];
        $this->assertSame('GET', $method);
        $this->assertStringStartsWith('https://www.textmagic.com/app/api?', $url);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(
            [
                'cmd' => 'send',
                'unicode' => '0',
                'from' => '421900000000',
                'username' => 'u',
                'password' => 's3cr3t',
                'phone' => '421951111111',
                'text' => 'Hello world',
            ],
            $query
        );
    }

    public function testRejectedRequestRaisesWithoutTheUrl(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $caught = $this->sendAndCatch($this->connector($httpClient));

        $this->assertInstanceOf(\RuntimeException::class, $caught, 'A rejected request must raise');
        $this->assertStringContainsString('500', $caught->getMessage());
        $this->assertSecretsNotExposed($caught);
    }

    public function testTransportFailureRaisesWithoutTheUrl(): void
    {
        // HttpClient's own transport errors quote the full URL, password included.
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host']));

        $caught = $this->sendAndCatch($this->connector($httpClient));

        $this->assertInstanceOf(\RuntimeException::class, $caught, 'A transport failure must raise');
        $this->assertSecretsNotExposed($caught);
        $this->assertNull($caught->getPrevious(), 'A chained exception would carry the URL to Sentry');
    }

    private function connector(MockHttpClient $httpClient): TextmagicSmsConnector
    {
        return new TextmagicSmsConnector(new RequestStack(), $httpClient, self::CONFIG);
    }

    private function sendAndCatch(TextmagicSmsConnector $connector): ?\Throwable
    {
        try {
            $connector->send('421951111111', 'Hello world');
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    private function assertSecretsNotExposed(\Throwable $exception): void
    {
        $this->assertStringContainsString('421951111111', $exception->getMessage());
        for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
            $this->assertStringNotContainsString('s3cr3t', $e->getMessage());
            $this->assertStringNotContainsString('textmagic.com/app/api', $e->getMessage());
        }
    }
}
