<?php

declare(strict_types=1);

namespace Test\BikeShare\SmsConnector;

use BikeShare\SmsConnector\EuroSmsConnector;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class EuroSmsConnectorTest extends TestCase
{
    use PHPMock;

    public function testCheckConfig()
    {
        $configuration = [
            'eurosms' => [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ]
        ];

        $request = $this->createMock(Request::class);
        $smsConnector = new EuroSmsConnector(
            $request,
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
        $configuration = [
            'eurosms' => [
                'gatewayId' => $gatewayId,
                'gatewayKey' => $gatewayKey,
                'gatewaySenderNumber' => $gatewaySenderNumber,
            ]
        ];
        $request = $this->createMock(Request::class);
        $smsConnector = new EuroSmsConnector(
            $request,
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
        $configuration = [
            'eurosms' => [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ]
        ];
        $request = $this->createMock(Request::class);
        $smsConnector = new EuroSmsConnector(
            $request,
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
        $configuration = [
            'eurosms' => [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ]
        ];
        $request = $this->createMock(Request::class);
        $smsConnector = new EuroSmsConnector(
            $request,
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
