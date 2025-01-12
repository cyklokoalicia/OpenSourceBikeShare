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
        $isCreditSystemEnabled,
        $expectedSystemClass
    ) {
        $serviceLocatorMock = $this->createMock(ServiceLocator::class);
        $factory = new CreditSystemFactory(
            $serviceLocatorMock,
            $isCreditSystemEnabled
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
            'isCreditSystemEnabled' => false,
            'expectedSystemClass' => DisabledCreditSystem::class
        ];
        yield 'enabled credit system' => [
            'isCreditSystemEnabled' => true,
            'expectedSystemClass' => CreditSystem::class
        ];
    }
}
