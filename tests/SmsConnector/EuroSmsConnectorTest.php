<?php

namespace Test\BikeShare\SmsConnector;

use BikeShare\SmsConnector\EuroSmsConnector;
use PHPUnit\Framework\TestCase;

class EuroSmsConnectorTest extends TestCase
{
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
        $this->smsConnector->send('number', 'text');
        $this->expectOutputString('');
    }
}

/**
 * @phpcs:disable PSR1.Files.SideEffects
 */
namespace BikeShare\SmsConnector;
{

/**
 * no need to send real request to as.eurosms.com
 * TODO should be refactored to use Guzzle or similar
 */
function fopen($filename, $mode, $use_include_path = null, $context = null)
{
    $gatewayId = 'Id';
    $gatewayKey = 'Key';
    $gatewaySenderNumber = 'SenderNumber';
    $message = 'text';
    $number = 'number';
    $s = substr(md5($gatewayKey . $number), 10, 11);
    $um = urlencode($message);

    $url = sprintf(
        'http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=%s&s=%s&d=1&sender=%s&number=%s&msg=%s',
        $gatewayId,
        $s,
        $gatewaySenderNumber,
        $number,
        $um
    );

    if ($filename !== $url) {
        throw new \RuntimeException('Invalid URL generated');
    }
}

}