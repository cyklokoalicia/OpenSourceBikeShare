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
    public function testRequiresLegacyFieldNames(string $userAgent, bool $expectedLegacy): void
    {
        $request = Request::create('/api/v1/admin/users');
        $request->headers->set('User-Agent', $userAgent);

        $this->assertSame($expectedLegacy, $this->detector->requiresLegacyFieldNames($request));
    }

    public static function userAgentProvider(): array
    {
        return [
            'new Android 1.0.1' => ['BikeShare-Android/1.0.1 (2)', false],
            'new Android 2.0.0' => ['BikeShare-Android/2.0.0 (5)', false],
            'new Android 1.1.0' => ['BikeShare-Android/1.1.0 (3)', false],
            'old Android 1.0.0' => ['BikeShare-Android/1.0.0 (1)', true],
            'old Android 0.9.0' => ['BikeShare-Android/0.9.0 (1)', true],
            'old Android okhttp' => ['okhttp/4.12.0', true],
            'browser' => ['Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36', false],
            'empty UA' => ['', false],
            'curl' => ['curl/8.5.0', false],
            'custom app name' => ['WhiteBikes-Android/1.0.1 (2)', false],
            'custom app name old' => ['WhiteBikes-Android/1.0.0 (1)', true],
        ];
    }

    public function testNoUserAgentHeader(): void
    {
        $request = Request::create('/api/v1/admin/users');
        $request->headers->remove('User-Agent');

        $this->assertFalse($this->detector->requiresLegacyFieldNames($request));
    }
}
