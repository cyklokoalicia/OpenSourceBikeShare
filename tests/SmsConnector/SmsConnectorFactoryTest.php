<?php

namespace Test\BikeShare\SmsConnector;

use BikeShare\App\Configuration;
use BikeShare\SmsConnector\DisabledConnector;
use BikeShare\SmsConnector\EuroSmsConnector;
use BikeShare\SmsConnector\LoopbackConnector;
use BikeShare\SmsConnector\SmsConnectorFactory;
use BikeShare\SmsConnector\SmsGatewayConnector;
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
        $config,
        $debugMode,
        $expectedInstance,
        $expectedExceptionMessage = null
    ) {
        $logger = $this->createMock(LoggerInterface::class);
        $configuration = $this->createMock(Configuration::class);
        $serviceLocatorMock = $this->createMock(ServiceLocator::class);

        if ($expectedExceptionMessage) {
            $serviceLocatorMock
                ->expects($this->once())
                ->method('get')
                ->with($config['sms'])
                ->willThrowException(new \Exception($expectedExceptionMessage));
        } else {
            $serviceLocatorMock
                ->expects($this->once())
                ->method('get')
                ->with($config['sms'])
                ->willReturn($this->createMock($expectedInstance));
        }

        $smsConnectorFactory = new SmsConnectorFactory($config, $serviceLocatorMock, $configuration, $logger);

        if ($expectedExceptionMessage) {
            $logger
                ->expects($this->once())
                ->method('error')
                ->with(
                    'Error creating SMS connector',
                    $this->callback(fn($context) => $context['connector'] === $config['sms']
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
            'config' => [
                'sms' => 'loopback',
            ],
            'debugMode' => true,
            'expectedInstance' => LoopbackConnector::class,
        ];
        yield 'eurosms' => [
            'config' => [
                'sms' => 'eurosms',
            ],
            'debugMode' => true,
            'expectedInstance' => EuroSmsConnector::class,
        ];
        yield 'smsgateway' => [
            'config' => [
                'sms' => 'smsgateway.me',
            ],
            'debugMode' => true,
            'expectedInstance' => SmsGatewayConnector::class,
        ];
        yield 'textmagic' => [
            'config' => [
                'sms' => 'textmagic.com',
            ],
            'debugMode' => true,
            'expectedInstance' => TextmagicSmsConnector::class,
        ];
        yield 'unknown' => [
            'config' => [
                'sms' => 'unknown',
            ],
            'debugMode' => true,
            'expectedInstance' => DisabledConnector::class,
        ];

        yield 'throwException' => [
            'config' => [
                'sms' => 'eurosms',
            ],
            'debugMode' => false,
            'expectedInstance' => DisabledConnector::class,
            'expectedExceptionMessage' => 'Invalid EuroSms configuration',
        ];
    }
}
