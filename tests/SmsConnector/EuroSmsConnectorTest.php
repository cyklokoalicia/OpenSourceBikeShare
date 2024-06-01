<?php

namespace Test\BikeShare\SmsConnector;

use BikeShare\App\Configuration;
use BikeShare\SmsConnector\EuroSmsConnector;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class EuroSmsConnectorTest extends TestCase
{
    use PHPMock;

    public function testCheckConfig()
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration
            ->expects($this->once())
            ->method('get')
            ->with('connectors')
            ->willReturn(
                json_encode(
                    [
                        'config' => [
                            'eurosms' => [
                                'gatewayId' => 'Id',
                                'gatewayKey' => 'Key',
                                'gatewaySenderNumber' => 'SenderNumber',
                            ]
                        ]
                    ]
                )
            );
        $smsConnector = new EuroSmsConnector(
            $configuration,
            false
        );

        $reflection = new \ReflectionClass($smsConnector);
        $gatewayId = $reflection->getProperty('gatewayId');
        $gatewayId->setAccessible(true);
        $gatewayKey = $reflection->getProperty('gatewayKey');
        $gatewayKey->setAccessible(true);
        $gatewaySenderNumber = $reflection->getProperty('gatewaySenderNumber');
        $gatewaySenderNumber->setAccessible(true);

        $this->assertEquals('Id', $gatewayId->getValue($smsConnector));
        $this->assertEquals('Key', $gatewayKey->getValue($smsConnector));
        $this->assertEquals('SenderNumber', $gatewaySenderNumber->getValue($smsConnector));
    }

    /**
     * @dataProvider checkConfigErrorDataProvider
     * @param $gatewayId
     * @param $gatewayKey
     * @param $gatewaySenderNumber
     * @return void
     */
    public function testCheckConfigError(
        $gatewayId,
        $gatewayKey,
        $gatewaySenderNumber
    ) {
        $this->expectException(\RuntimeException::class);
        $configuration = $this->createMock(Configuration::class);
        $configuration
            ->expects($this->once())
            ->method('get')
            ->with('connectors')
            ->willReturn(
                json_encode(
                    [
                        'config' => [
                            'eurosms' => [
                                'gatewayId' => $gatewayId,
                                'gatewayKey' => $gatewayKey,
                                'gatewaySenderNumber' => $gatewaySenderNumber,
                            ]
                        ]
                    ]
                )
            );
        $smsConnector = new EuroSmsConnector(
            $configuration,
            false
        );
    }


    public function checkConfigErrorDataProvider()
    {
        yield 'gatewayId is empty' => [
            '',
            'Key',
            'SenderNumber',
        ];
        yield 'gatewayKey is empty' => [
            'Id',
            '',
            'SenderNumber',
        ];
        yield 'gatewaySenderNumber is empty' => [
            'Id',
            'Key',
            '',
        ];
    }

    public function testRespond()
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration
            ->expects($this->once())
            ->method('get')
            ->with('connectors')
            ->willReturn(
                json_encode(
                    [
                        'config' => [
                            'eurosms' => [
                                'gatewayId' => 'Id',
                                'gatewayKey' => 'Key',
                                'gatewaySenderNumber' => 'SenderNumber',
                            ]
                        ]
                    ]
                )
            );
        $smsConnector = new EuroSmsConnector(
            $configuration,
            false
        );

        $reflection = new \ReflectionClass($smsConnector);
        $uuid = $reflection->getProperty('uuid');
        $uuid->setAccessible(true);
        $uuid->setValue($smsConnector, 'uuid');

        $this->expectOutputString('ok:uuid' . "\n");
        $smsConnector->respond();
    }

    public function testSend()
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration
            ->expects($this->once())
            ->method('get')
            ->with('connectors')
            ->willReturn(
                json_encode(
                    [
                        'config' => [
                            'eurosms' => [
                                'gatewayId' => 'Id',
                                'gatewayKey' => 'Key',
                                'gatewaySenderNumber' => 'SenderNumber',
                            ]
                        ]
                    ]
                )
            );
        $smsConnector = new EuroSmsConnector(
            $configuration,
            false
        );

        $this->getFunctionMock('BikeShare\SmsConnector', 'md5')
            ->expects($this->once())
            ->with('Key' . 'number')
            ->willReturn('123456789011223344556677889900aa');
        $this->getFunctionMock('BikeShare\SmsConnector', 'substr')
            ->expects($this->once())
            ->with('123456789011223344556677889900aa', 10, 11)
            ->willReturn('1122334455');
        $this->getFunctionMock('BikeShare\SmsConnector', 'urlencode')
            ->expects($this->once())
            ->with('text!@-_ +')
            ->willReturn(urlencode('text!@-_ +'));

        $expectedUrl = 'http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=Id&'
            . 's=1122334455&d=1&sender=SenderNumber&number=number&msg=text%21%40-_+%2B';

        $this->getFunctionMock('BikeShare\SmsConnector', 'fopen')
            ->expects($this->once())
            ->with(
                $expectedUrl,
                'r'
            )->willReturn(true);

        $smsConnector->send('number', 'text!@-_ +');
        $this->expectOutputString('');
    }
}
