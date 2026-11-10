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

    public function testSendPostsTheCredentialsInTheBody(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requests): MockResponse {
                $requests[] = [$method, $url, $options];

                return new MockResponse(
                    '{"message_id":{"1":"421951111111"},"sent_text":"Hello world","parts_count":1}',
                    ['http_code' => 200]
                );
            }
        );

        $this->connector($httpClient)->send('421951111111', 'Hello world');

        $this->assertCount(1, $requests);
        [$method, $url, $options] = $requests[0];
        $this->assertSame('POST', $method);
        // HttpClient's log and Sentry's HTTP tracing record the URL, so nothing secret may be in it.
        $this->assertSame('https://www.textmagic.com/app/api?cmd=send', $url);
        parse_str($options['body'], $body);
        $this->assertSame(
            [
                'unicode' => '0',
                'from' => '421900000000',
                'username' => 'u',
                'password' => 's3cr3t',
                'phone' => '421951111111',
                'text' => 'Hello world',
            ],
            $body
        );
    }

    public function testSendIsBoundedInTime(): void
    {
        $maxDuration = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$maxDuration): MockResponse {
                $maxDuration = $options['max_duration'];

                return new MockResponse('{}', ['http_code' => 200]);
            }
        );

        $this->connector($httpClient)->send('421951111111', 'Hello world');

        // An unbounded request would hold the inbound SMS request until the gateway redelivers it.
        $this->assertGreaterThan(0, $maxDuration);
        $this->assertLessThanOrEqual(10, $maxDuration);
    }

    public function testErrorCodeInTheResponseRaises(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse(
                '{"error_code":11,"error_message":"Your daily API usage limit is reached"}',
                ['http_code' => 200]
            )
        );

        $caught = $this->sendAndCatch($this->connector($httpClient));

        $this->assertInstanceOf(\RuntimeException::class, $caught, 'An error_code response must raise');
        $this->assertStringContainsString('error code 11', $caught->getMessage());
        $this->assertSecretsNotExposed($caught);
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
