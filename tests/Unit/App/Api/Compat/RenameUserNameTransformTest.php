<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\Api\Compat;

use BikeShare\App\Api\Compat\RenameUserNameTransform;
use PHPUnit\Framework\TestCase;

class RenameUserNameTransformTest extends TestCase
{
    private RenameUserNameTransform $transform;

    protected function setUp(): void
    {
        $this->transform = new RenameUserNameTransform();
    }

    public function testSinceVersion(): void
    {
        $this->assertSame('1.0.1', $this->transform->getSinceVersion());
    }

    public function testRoutesAreSpecific(): void
    {
        $routes = $this->transform->getRoutes();
        $this->assertNotEmpty($routes);
        $this->assertContains('api_v1_admin_users', $routes);
        $this->assertContains('api_v1_admin_report_users', $routes);
    }

    public function testRenamesFlatData(): void
    {
        $data = [
            ['userId' => 1, 'userName' => 'John', 'mail' => 'john@test.com'],
            ['userId' => 2, 'userName' => 'Jane', 'mail' => 'jane@test.com'],
        ];

        $result = $this->transform->transform($data);

        $this->assertSame('John', $result[0]['username']);
        $this->assertArrayNotHasKey('userName', $result[0]);
        $this->assertSame('john@test.com', $result[0]['mail']);
    }

    public function testRenamesNestedData(): void
    {
        $data = [
            'notes' => 'some notes',
            'history' => [
                ['action' => 'RENT', 'userName' => 'John'],
            ],
        ];

        $result = $this->transform->transform($data);

        $this->assertSame('John', $result['history'][0]['username']);
        $this->assertArrayNotHasKey('userName', $result['history'][0]);
    }

    public function testPreservesOtherFields(): void
    {
        $data = [['userId' => 1, 'mail' => 'test@test.com']];

        $result = $this->transform->transform($data);

        $this->assertSame(1, $result[0]['userId']);
        $this->assertSame('test@test.com', $result[0]['mail']);
    }
}
