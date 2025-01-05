<?php

declare(strict_types=1);

namespace Test\BikeShare\SmsConnector;

use BikeShare\SmsConnector\DisabledConnector;
use BikeShare\SmsConnector\EuroSmsConnector;
use BikeShare\SmsConnector\LoopbackConnector;
use BikeShare\SmsConnector\SmsConnectorFactory;
use BikeShare\SmsConnector\TextmagicSmsConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class SmsConnectorFactoryTest extends TestCase
{
    /**
     * @param array $config
     * @param bool $debugMode
     * @param string $expectedInstance
     * @param string|null $expectedException
     * @dataProvider getConnectorDataProvider
     */
    public function testGetConnector(
        $connectorName,
        $debugMode,
        $expectedInstance,
        $expectedExceptionMessage = null
    ) {
        $logger = $this->createMock(LoggerInterface::class);
        $serviceLocatorMock = $this->createMock(ServiceLocator::class);

        if ($expectedExceptionMessage) {
            $serviceLocatorMock
                ->expects($this->once())
                ->method('get')
                ->with($connectorName)
                ->willThrowException(new \Exception($expectedExceptionMessage));
        } else {
            $serviceLocatorMock
                ->expects($this->once())
                ->method('get')
                ->with($connectorName)
                ->willReturn($this->createMock($expectedInstance));
        }

        $smsConnectorFactory = new SmsConnectorFactory($connectorName, $serviceLocatorMock, $logger);

        if ($expectedExceptionMessage) {
            $logger
                ->expects($this->once())
                ->method('error')
                ->with(
                    'Error creating SMS connector',
                    $this->callback(fn($context) => $context['connector'] === $connectorName
                        && $context['exception'] instanceof \Exception
                        && $context['exception']->getMessage() === $expectedExceptionMessage)
                );
        }
        $result = $smsConnectorFactory->getConnector();
        $this->assertInstanceOf($expectedInstance, $result);
    }

    public function getConnectorDataProvider()
    {
        yield 'loopback' => [
            'connectorName' => 'loopback',
            'debugMode' => true,
            'expectedInstance' => LoopbackConnector::class,
        ];
        yield 'eurosms' => [
            'connectorName' => 'eurosms',
            'debugMode' => true,
            'expectedInstance' => EuroSmsConnector::class,
        ];
        yield 'textmagic' => [
            'connectorName' => 'textmagic.com',
            'debugMode' => true,
            'expectedInstance' => TextmagicSmsConnector::class,
        ];
        yield 'unknown' => [
            'connectorName' => 'unknown',
            'debugMode' => true,
            'expectedInstance' => DisabledConnector::class,
        ];

        yield 'throwException' => [
            'connectorName' => 'eurosms',
            'debugMode' => false,
            'expectedInstance' => DisabledConnector::class,
            'expectedExceptionMessage' => 'Invalid EuroSms configuration',
        ];
    }
}
