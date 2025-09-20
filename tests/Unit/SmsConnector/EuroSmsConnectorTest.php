<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsConnector;

use BikeShare\SmsConnector\EuroSmsConnector;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EuroSmsConnectorTest extends TestCase
{
    use PHPMock;

    public function testCheckConfig()
    {
        $configuration = [
            'eurosms' => [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ]
        ];

        $requestStack = $this->createMock(RequestStack::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $smsConnector = new EuroSmsConnector(
            $requestStack,
            $httpClient,
            $configuration,
            false
        );

        $reflection = new \ReflectionClass($smsConnector);
        $gatewayId = $reflection->getProperty('gatewayId');
        $gatewayId->setAccessible(true);
        $gatewayKey = $reflection->getProperty('gatewayKey');
        $gatewayKey->setAccessible(true);
        $gatewaySenderNumber = $reflection->getProperty('gatewaySenderNumber');
        $gatewaySenderNumber->setAccessible(true);

        $this->assertEquals('Id', $gatewayId->getValue($smsConnector));
        $this->assertEquals('Key', $gatewayKey->getValue($smsConnector));
        $this->assertEquals('SenderNumber', $gatewaySenderNumber->getValue($smsConnector));
    }

    /**
     * @dataProvider checkConfigErrorDataProvider
     */
    public function testCheckConfigError(
        ?string $gatewayId,
        ?string $gatewayKey,
        ?string $gatewaySenderNumber
    ) {
        $this->expectException(\RuntimeException::class);
        $configuration = [
            'eurosms' => [
                'gatewayId' => $gatewayId,
                'gatewayKey' => $gatewayKey,
                'gatewaySenderNumber' => $gatewaySenderNumber,
            ]
        ];
        $requestStack = $this->createMock(RequestStack::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        new EuroSmsConnector(
            $requestStack,
            $httpClient,
            $configuration,
            false
        );
    }


    public function checkConfigErrorDataProvider(): \Generator
    {
        yield 'gatewayId is empty' => [
            '',
            'Key',
            'SenderNumber',
        ];
        yield 'gatewayKey is empty' => [
            'Id',
            '',
            'SenderNumber',
        ];
        yield 'gatewaySenderNumber is empty' => [
            'Id',
            'Key',
            '',
        ];
    }

    public function testRespond()
    {
        $configuration = [
            'eurosms' => [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ]
        ];
        $requestStack = $this->createMock(RequestStack::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $smsConnector = new EuroSmsConnector(
            $requestStack,
            $httpClient,
            $configuration,
            false
        );

        $reflection = new \ReflectionClass($smsConnector);
        $uuid = $reflection->getProperty('uuid');
        $uuid->setAccessible(true);
        $uuid->setValue($smsConnector, 'uuid');

        $this->expectOutputString('ok:uuid' . "\n");
        $smsConnector->respond();
    }

    public function testSend()
    {
        $configuration = [
            'eurosms' => [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ]
        ];

        $timestamp = 1600264980;
        $nonce = 'mynonce';

        $this->getFunctionMock('BikeShare\SmsConnector', 'time')
            ->expects($this->once())
            ->willReturn($timestamp);
        $this->getFunctionMock('BikeShare\SmsConnector', 'uniqid')
            ->expects($this->once())
            ->willReturn($nonce);

        $requestString = "{$timestamp}\n{$nonce}\nPOST\n/v2/sms/\nrest.eurosms.com\n443\n\n";
        $mac = base64_encode(hash_hmac('sha256', $requestString, 'Key', true));

        $expectedHeaders = [
            'Authorization' => sprintf(
                'MAC id="%s", ts="%s", nonce="%s", mac="%s"',
                'Id',
                $timestamp,
                $nonce,
                $mac
            ),
            'Content-Type' => 'application/json',
        ];

        $expectedPayload = [
            'messages' => [
                [
                    'destination' => '123456789',
                    'message' => 'Hello World',
                    'origin' => 'SenderNumber',
                ],
            ],
        ];

        $mockResponse = new MockResponse('', ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);

        $requestStack = $this->createMock(RequestStack::class);
        $smsConnector = new EuroSmsConnector(
            $requestStack,
            $httpClient,
            $configuration,
            false
        );

        $smsConnector->send('123456789', 'Hello World');

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame('https://rest.eurosms.com/v2/sms/', $mockResponse->getRequestUrl());
        $this->assertContains('Authorization: ' . $expectedHeaders['Authorization'], $mockResponse->getRequestOptions()['headers']);
        $this->assertContains('Content-Type: application/json', $mockResponse->getRequestOptions()['headers']);
        $this->assertSame(json_encode($expectedPayload), $mockResponse->getRequestOptions()['body']);
    }
}
