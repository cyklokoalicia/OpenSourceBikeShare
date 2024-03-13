<?php

namespace Test\BikeShare\SmsConnector;

use BikeShare\SmsConnector\EuroSmsConnector;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class EuroSmsConnectorTest extends TestCase
{
    use PHPMock;

    /**
     * @var EuroSmsConnector
     */
    private $smsConnector;

    protected function setUp()
    {
        $this->smsConnector = new EuroSmsConnector(
            [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ],
            false
        );
    }

    protected function tearDown()
    {
        unset($this->smsConnector);
    }

    public function testCheckConfig()
    {
        $this->smsConnector->checkConfig(
            [
                'gatewayId' => 'Id',
                'gatewayKey' => 'Key',
                'gatewaySenderNumber' => 'SenderNumber',
            ]
        );

        $reflection = new \ReflectionClass($this->smsConnector);
        $gatewayId = $reflection->getProperty('gatewayId');
        $gatewayId->setAccessible(true);
        $gatewayKey = $reflection->getProperty('gatewayKey');
        $gatewayKey->setAccessible(true);
        $gatewaySenderNumber = $reflection->getProperty('gatewaySenderNumber');
        $gatewaySenderNumber->setAccessible(true);

        $this->assertEquals('Id', $gatewayId->getValue($this->smsConnector));
        $this->assertEquals('Key', $gatewayKey->getValue($this->smsConnector));
        $this->assertEquals('SenderNumber', $gatewaySenderNumber->getValue($this->smsConnector));
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
        $this->smsConnector->checkConfig(
            [
                'gatewayId' => $gatewayId,
                'gatewayKey' => $gatewayKey,
                'gatewaySenderNumber' => $gatewaySenderNumber,
            ]
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
        $reflection = new \ReflectionClass($this->smsConnector);
        $uuid = $reflection->getProperty('uuid');
        $uuid->setAccessible(true);
        $uuid->setValue($this->smsConnector, 'uuid');

        $this->expectOutputString('ok:uuid' . "\n");
        $this->smsConnector->respond();
    }

    public function testSend()
    {
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

        $this->smsConnector->send('number', 'text!@-_ +');
        $this->expectOutputString('');
    }
}
