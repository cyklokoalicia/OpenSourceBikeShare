<?php

namespace Test\BikeShare\SmsConnector;

use BikeShare\SmsConnector\DisabledConnector;
use BikeShare\SmsConnector\EuroSmsConnector;
use BikeShare\SmsConnector\LoopbackConnector;
use BikeShare\SmsConnector\SmsConnectorFactory;
use BikeShare\SmsConnector\SmsGatewayConnector;
use BikeShare\SmsConnector\TextmagicSmsConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SmsConnectorFactoryTest extends TestCase
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
        $logger = $this->createMock(LoggerInterface::class);
        $smsConnectorFactory = new SmsConnectorFactory($logger);

        if ($expectedExceptionMessage) {
            $logger
                ->expects($this->once())
                ->method('error')
                ->with(
                    'Error creating SMS connector',
                    $this->callback(fn($context) => $context['connector'] === $connector
                        && $context['exception'] instanceof \Exception
                        && $context['exception']->getMessage() === $expectedExceptionMessage)
                );
        }
        $result = $smsConnectorFactory->getConnector($connector, $config, $debugMode);
        $this->assertInstanceOf($expectedInstance, $result);
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

        yield 'throwException' => [
            'connector' => 'eurosms',
            'config' => [],
            'debugMode' => false,
            'expectedInstance' => DisabledConnector::class,
            'expectedExceptionMessage' => 'Invalid EuroSms configuration',
        ];
    }
}
