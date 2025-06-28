<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsConnector;

use BikeShare\SmsConnector\DebugConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class DebugConnectorTest extends TestCase
{
    public function testSend()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $requestMock = $this->createMock(Request::class);
        $debugConnector = new DebugConnector(
            $requestMock,
            $loggerMock,
            [], #$configuration
            true
        );

        $loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('123456789 -&gt Hello, World!');

        $debugConnector->send('123456789', 'Hello, World!');
    }
}
