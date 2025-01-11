<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\Credit;

use BikeShare\Credit\CreditSystem;
use BikeShare\Credit\CreditSystemFactory;
use BikeShare\Credit\DisabledCreditSystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class CreditSystemFactoryTest extends TestCase
{
    /**
     * @dataProvider creditSystemDataProvider
     */
    public function testGetCreditSystem(
        $configuration,
        $expectedSystemClass
    ) {
        $serviceLocatorMock = $this->createMock(ServiceLocator::class);
        $factory = new CreditSystemFactory(
            $serviceLocatorMock,
            $configuration
        );

        $serviceLocatorMock->expects($this->once())
            ->method('get')
            ->with($expectedSystemClass)
            ->willReturn($this->createMock($expectedSystemClass));

        $this->assertInstanceOf(
            $expectedSystemClass,
            $factory->getCreditSystem()
        );
    }

    public function creditSystemDataProvider()
    {
        yield 'disabled credit system' => [
            'configuration' => [],
            'expectedSystemClass' => DisabledCreditSystem::class
        ];
        yield 'enabled credit system' => [
            'configuration' => [
                'enabled' => true
            ],
            'expectedSystemClass' => CreditSystem::class
        ];
    }
}
