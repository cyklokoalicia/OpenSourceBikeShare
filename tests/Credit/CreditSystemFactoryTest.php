<?php

namespace Credit;

use BikeShare\Credit\CreditSystem;
use BikeShare\Credit\CreditSystemFactory;
use BikeShare\Credit\DisabledCreditSystem;
use BikeShare\Db\DbInterface;
use PHPUnit\Framework\TestCase;

class CreditSystemFactoryTest extends TestCase
{
    /**
     * @dataProvider creditSystemDataProvider
     */
    public function testGetCreditSystem(
        $configuration,
        $expectedSystemClass
    ) {
        $factory = new CreditSystemFactory();
        $this->assertInstanceOf(
            $expectedSystemClass,
            $factory->getCreditSystem($configuration, $this->createMock(DbInterface::class))
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
