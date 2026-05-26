<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Sentry;

use BikeShare\Sentry\GbfsTracesSampler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sentry\Tracing\SamplingContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class GbfsTracesSamplerTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testSampleRateForPath(?string $path, float $expected): void
    {
        $requestStack = new RequestStack();
        if ($path !== null) {
            $requestStack->push(Request::create($path));
        }

        $sampler = new GbfsTracesSampler($requestStack);

        $this->assertSame($expected, $sampler(new SamplingContext()));
    }

    public static function pathProvider(): iterable
    {
        yield 'gbfs manifest' => ['/gbfs.json', 0.01];
        yield 'gbfs station status' => ['/gbfs/en/station_status.json', 0.01];
        yield 'gbfs vehicle types' => ['/gbfs/sk/vehicle_types.json', 0.01];
        yield 'regular admin page' => ['/admin', 1.0];
        yield 'home' => ['/', 1.0];
        yield 'no main request (CLI/messenger)' => [null, 1.0];
    }
}
