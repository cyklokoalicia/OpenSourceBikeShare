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

    private const API_HOST = 'https://as.eurosms.com/api/v3/send/one';
    private const API_HOST_TEST = 'https://as.eurosms.com/api/v3/test/one';

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
            $configuration
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
            $configuration
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
            $configuration
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

        $mockResponse = new MockResponse('', ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);

        $requestStack = $this->createMock(RequestStack::class);
        $smsConnector = new EuroSmsConnector(
            $requestStack,
            $httpClient,
            $configuration
        );

        $phoneNumber = '123456789';
        $message = 'Hello World';

        // Create a spy for the calculateSignature method
        $reflection = new \ReflectionClass($smsConnector);
        $calculateSignature = $reflection->getMethod('calculateSignature');
        $calculateSignature->setAccessible(true);
        $expectedSignature = $calculateSignature->invoke($smsConnector, $phoneNumber, $message);

        $smsConnector->send($phoneNumber, $message);

        $this->assertSame('POST', $mockResponse->getRequestMethod());
        $this->assertSame(self::API_HOST, $mockResponse->getRequestUrl());
        $this->assertContains('Content-Type: application/json', $mockResponse->getRequestOptions()['headers']);

        $requestPayload = json_decode($mockResponse->getRequestOptions()['body'], true);
        $this->assertArrayHasKey('iid', $requestPayload);
        $this->assertArrayHasKey('sgn', $requestPayload);
        $this->assertArrayHasKey('rcpt', $requestPayload);
        $this->assertArrayHasKey('flgs', $requestPayload);
        $this->assertArrayHasKey('sndr', $requestPayload);
        $this->assertArrayHasKey('txt', $requestPayload);

        $this->assertEquals('Id', $requestPayload['iid']);
        $this->assertEquals($expectedSignature, $requestPayload['sgn']);
        $this->assertEquals((int)$phoneNumber, $requestPayload['rcpt']);
        $this->assertEquals(
            EuroSmsConnector::FLAG_DELIVERY | EuroSmsConnector::FLAG_LONG_SMS | EuroSmsConnector::FLAG_DIACRITIC,
            $requestPayload['flgs']
        );
        $this->assertEquals('SenderNumber', $requestPayload['sndr']);
        $this->assertEquals($message, $requestPayload['txt']);
    }

    public function testCalculateSignature()
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
            $configuration
        );

        $reflection = new \ReflectionClass($smsConnector);
        $calculateSignature = $reflection->getMethod('calculateSignature');
        $calculateSignature->setAccessible(true);

        $number = '123456789';
        $text = 'Hello World';
        $string = sprintf(
            '%s%s%s',
            'SenderNumber',
            $number,
            $text
        );
        $expectedHash = hash_hmac('sha256', $string, 'Key');

        $this->assertEquals($expectedHash, $calculateSignature->invoke($smsConnector, $number, $text));
    }
}
