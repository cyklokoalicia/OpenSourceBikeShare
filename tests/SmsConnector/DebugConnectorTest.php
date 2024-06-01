<?php

namespace Test\BikeShare\SmsConnector;

use BikeShare\App\Configuration;
use BikeShare\SmsConnector\DebugConnector;
use PHPUnit\Framework\TestCase;

class DebugConnectorTest extends TestCase
{
    public function testSend()
    {
        $debugConnector = new DebugConnector(
            $this->createMock(Configuration::class),
            true
        );
        $debugConnector->send('123456789', 'Hello, World!');
        $this->expectOutputString('123456789 -&gt Hello, World!' . PHP_EOL);
    }
}
