<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\Api\Compat;

use BikeShare\App\Api\Compat\ApiCompatTransformRegistry;
use BikeShare\App\Api\Compat\ApiResponseTransformInterface;
use PHPUnit\Framework\TestCase;

class ApiCompatTransformRegistryTest extends TestCase
{
    public function testOldClientGetsAllTransforms(): void
    {
        $registry = new ApiCompatTransformRegistry([
            $this->createTransform('1.0.1', ['route_a']),
            $this->createTransform('1.1.0', ['route_a']),
        ]);

        $transforms = $registry->getTransformsFor('1.0.0', 'route_a');
        $this->assertCount(2, $transforms);
    }

    public function testNewClientGetsNoTransforms(): void
    {
        $registry = new ApiCompatTransformRegistry([
            $this->createTransform('1.0.1', ['route_a']),
        ]);

        $transforms = $registry->getTransformsFor('1.0.1', 'route_a');
        $this->assertCount(0, $transforms);
    }

    public function testClientBetweenVersionsGetsPartialTransforms(): void
    {
        $registry = new ApiCompatTransformRegistry([
            $this->createTransform('1.0.1', ['route_a']),
            $this->createTransform('1.2.0', ['route_a']),
        ]);

        $transforms = $registry->getTransformsFor('1.0.1', 'route_a');
        $this->assertCount(1, $transforms);
        $this->assertSame('1.2.0', $transforms[0]->getSinceVersion());
    }

    public function testRouteFilteringWorks(): void
    {
        $registry = new ApiCompatTransformRegistry([
            $this->createTransform('1.0.1', ['route_a']),
            $this->createTransform('1.0.1', ['route_b']),
        ]);

        $transforms = $registry->getTransformsFor('1.0.0', 'route_a');
        $this->assertCount(1, $transforms);
    }

    public function testEmptyRoutesAppliesGlobally(): void
    {
        $registry = new ApiCompatTransformRegistry([
            $this->createTransform('1.0.1', []),
        ]);

        $transforms = $registry->getTransformsFor('1.0.0', 'any_route');
        $this->assertCount(1, $transforms);
    }

    public function testNewestFirstOrdering(): void
    {
        $registry = new ApiCompatTransformRegistry([
            $this->createTransform('1.0.1', ['route_a']),
            $this->createTransform('2.0.0', ['route_a']),
            $this->createTransform('1.5.0', ['route_a']),
        ]);

        $transforms = $registry->getTransformsFor('1.0.0', 'route_a');
        $this->assertCount(3, $transforms);
        $this->assertSame('2.0.0', $transforms[0]->getSinceVersion());
        $this->assertSame('1.5.0', $transforms[1]->getSinceVersion());
        $this->assertSame('1.0.1', $transforms[2]->getSinceVersion());
    }

    public function testEmptyRegistry(): void
    {
        $registry = new ApiCompatTransformRegistry([]);

        $transforms = $registry->getTransformsFor('1.0.0', 'route_a');
        $this->assertCount(0, $transforms);
    }

    private function createTransform(string $since, array $routes): ApiResponseTransformInterface
    {
        $transform = $this->createStub(ApiResponseTransformInterface::class);
        $transform->method('getSinceVersion')->willReturn($since);
        $transform->method('getRoutes')->willReturn($routes);

        return $transform;
    }
}
