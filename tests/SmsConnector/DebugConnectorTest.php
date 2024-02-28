<?php

namespace Test\BikeShare\SmsConnector;

use BikeShare\SmsConnector\DebugConnector;
use PHPUnit\Framework\TestCase;

class DebugConnectorTest extends TestCase
{
    public function testSend()
    {
        $debugConnector = new DebugConnector();
        $debugConnector->send('123456789', 'Hello, World!');
        $this->expectOutputString('123456789 -&gt Hello, World!'.PHP_EOL);
    }
}
