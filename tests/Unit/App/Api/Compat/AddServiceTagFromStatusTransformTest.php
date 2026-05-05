<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\Api\Compat;

use BikeShare\App\Api\Compat\AddServiceTagFromStatusTransform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddServiceTagFromStatusTransformTest extends TestCase
{
    private AddServiceTagFromStatusTransform $transform;

    protected function setUp(): void
    {
        $this->transform = new AddServiceTagFromStatusTransform();
    }

    public function testSinceVersion(): void
    {
        $this->assertSame('1.1.2', $this->transform->getSinceVersion());
    }

    public function testRoutesAreSpecific(): void
    {
        $routes = $this->transform->getRoutes();
        $this->assertNotEmpty($routes);
        $this->assertContains('api_v1_stand_markers', $routes);
        $this->assertContains('api_v1_stands', $routes);
        $this->assertContains('api_v1_admin_stand_item', $routes);
    }

    #[DataProvider('statusToServiceTagProvider')]
    public function testStatusMapsToServiceTag(string $status, int $expectedServiceTag): void
    {
        $data = [['standId' => 1, 'status' => $status]];

        $result = $this->transform->transform($data);

        $this->assertSame($expectedServiceTag, $result[0]['serviceTag']);
        $this->assertSame($status, $result[0]['status']);
    }

    public static function statusToServiceTagProvider(): iterable
    {
        yield 'active' => ['active', 0];
        yield 'technical' => ['technical', 1];
        yield 'hidden' => ['hidden', 1];
        yield 'inactive' => ['inactive', 0];
        yield 'virtual' => ['virtual', 0];
    }

    public function testSingleObjectIsTransformed(): void
    {
        $data = ['standId' => 1, 'standName' => 'STAND1', 'status' => 'active'];
        $result = $this->transform->transform($data);
        $this->assertSame(0, $result['serviceTag']);
    }

    public function testNestedArraysAreTransformed(): void
    {
        $data = [
            'wrapper' => [
                ['status' => 'technical', 'standName' => 'A'],
                ['status' => 'active', 'standName' => 'B'],
            ],
        ];

        $result = $this->transform->transform($data);

        $this->assertSame(1, $result['wrapper'][0]['serviceTag']);
        $this->assertSame(0, $result['wrapper'][1]['serviceTag']);
    }

    public function testItemsWithoutStatusAreLeftAlone(): void
    {
        $data = [['unrelated' => 'value']];
        $result = $this->transform->transform($data);
        $this->assertArrayNotHasKey('serviceTag', $result[0]);
    }
}
