<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\Api;

use BikeShare\App\Api\ClientVersionDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ClientVersionDetectorTest extends TestCase
{
    private ClientVersionDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new ClientVersionDetector();
    }

    #[DataProvider('userAgentProvider')]
    public function testGetClientVersion(string $userAgent, string $expectedVersion): void
    {
        $request = Request::create('/api/v1/admin/users');
        $request->headers->set('User-Agent', $userAgent);

        $this->assertSame($expectedVersion, $this->detector->getClientVersion($request));
    }

    public static function userAgentProvider(): array
    {
        return [
            'Android 1.0.1' => ['BikeShare-Android/1.0.1 (2)', '1.0.1'],
            'Android 2.0.0' => ['BikeShare-Android/2.0.0 (5)', '2.0.0'],
            'Android 1.0.0' => ['BikeShare-Android/1.0.0 (1)', '1.0.0'],
            'Android 0.9.0' => ['BikeShare-Android/0.9.0 (1)', '0.9.0'],
            'custom app name' => ['WhiteBikes-Android/1.2.3 (4)', '1.2.3'],
            'old Android okhttp' => ['okhttp/4.12.0', '0.0.0'],
            'browser' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36', '999.0.0'],
            'empty UA' => ['', '999.0.0'],
            'curl' => ['curl/8.5.0', '999.0.0'],
        ];
    }

    public function testNoUserAgentHeader(): void
    {
        $request = Request::create('/api/v1/admin/users');
        $request->headers->remove('User-Agent');

        $this->assertSame('999.0.0', $this->detector->getClientVersion($request));
    }
}
