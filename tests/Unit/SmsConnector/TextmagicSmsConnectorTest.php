<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsConnector;

use BikeShare\SmsConnector\TextmagicSmsConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class TextmagicSmsConnectorTest extends TestCase
{
    private const CONFIG = [
        'gatewayUser' => 'u',
        'gatewayPassword' => 's3cr3t',
        'gatewaySenderNumber' => '421900000000',
    ];

    public function testSendThrowsWhenTheRequestFails(): void
    {
        $connector = new class (new RequestStack(), ['textmagic' => self::CONFIG]) extends TextmagicSmsConnector {
            protected function openUrl(string $url): mixed
            {
                return false;
            }
        };

        $caught = null;
        try {
            $connector->send('421951111111', 'Hello world');
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $caught, 'A failed request must raise');
        $this->assertStringNotContainsString('s3cr3t', $caught->getMessage());
        $this->assertStringNotContainsString('textmagic.com/app/api', $caught->getMessage());
        $this->assertStringContainsString('421951111111', $caught->getMessage());
    }

    public function testSendBuildsTheRequestUrl(): void
    {
        $connector = new class (new RequestStack(), ['textmagic' => self::CONFIG]) extends TextmagicSmsConnector {
            public ?string $url = null;

            protected function openUrl(string $url): mixed
            {
                $this->url = $url;

                return fopen('php://memory', 'r');
            }
        };

        $connector->send('421951111111', 'Hello world');

        $this->assertStringStartsWith('https://www.textmagic.com/app/api?cmd=send', (string)$connector->url);
        $this->assertStringContainsString('phone=421951111111', (string)$connector->url);
        $this->assertStringContainsString('text=Hello+world', (string)$connector->url);
    }
}
