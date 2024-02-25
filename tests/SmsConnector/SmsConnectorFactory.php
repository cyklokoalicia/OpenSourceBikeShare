<?php

namespace Test\Bikeshare\SmsConnector;

use BikeShare\SmsConnector\DisabledConnector;
use BikeShare\SmsConnector\EuroSmsConnector;
use BikeShare\SmsConnector\LoopbackConnector;
use BikeShare\SmsConnector\SmsConnectorFactory as SmsConnectorFactoryAlias;
use BikeShare\SmsConnector\SmsGatewayConnector;
use BikeShare\SmsConnector\TextmagicSmsConnector;
use PHPUnit\Framework\TestCase;

class SmsConnectorFactory extends TestCase
{
    /**
     * @param string $connector
     * @param array $config
     * @param bool $debugMode
     * @param string $expectedInstance
     * @param string|null $expectedException
     * @dataProvider getConnectorDataProvider
     */
    public function testGetConnector(
        $connector,
        $config,
        $debugMode,
        $expectedInstance,
        $expectedExceptionMessage = null
    ) {
        $smsConnectorFactory = new SmsConnectorFactoryAlias();
        try {
            $result = $smsConnectorFactory->getConnector($connector, $config, $debugMode);
            $this->assertInstanceOf($expectedInstance, $result);
        } catch (\PHPUnit_Framework_Error_Warning $e) {
            $this->assertEquals($expectedExceptionMessage, $e->getMessage());
        }
    }

    public function getConnectorDataProvider()
    {

        yield 'loopback' => [
            'connector' => 'loopback',
            'config' => [],
            'debugMode' => true,
            'expectedInstance' => LoopbackConnector::class,
        ];
        yield 'eurosms' => [
            'connector' => 'eurosms',
            'config' => [],
            'debugMode' => true,
            'expectedInstance' => EuroSmsConnector::class,
        ];
        yield 'smsgateway' => [
            'connector' => 'smsgateway.me',
            'config' => [],
            'debugMode' => true,
            'expectedInstance' => SmsGatewayConnector::class,
        ];
        yield 'textmagic' => [
            'connector' => 'textmagic.com',
            'config' => [],
            'debugMode' => true,
            'expectedInstance' => TextmagicSmsConnector::class,
        ];
        yield 'unknown' => [
            'connector' => 'unknown',
            'config' => [],
            'debugMode' => true,
            'expectedInstance' => DisabledConnector::class,
        ];

        //PHPUNIT configured to convert warnings to exceptions so we test for exception message
        yield 'throwException' => [
            'connector' => 'eurosms',
            'config' => [],
            'debugMode' => false,
            'expectedInstance' => DisabledConnector::class,
            'expectedExceptionMessage' => 'Invalid EuroSms configuration',
        ];
    }
}