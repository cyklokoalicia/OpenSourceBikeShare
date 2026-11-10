<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsConnector;

use BikeShare\SmsConnector\DebugConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class DebugConnectorTest extends TestCase
{
    public function testSend()
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $requestStack = $this->createStub(RequestStack::class);
        $debugConnector = new DebugConnector(
            $requestStack,
            $loggerMock,
            [], #$configuration
        );

        $loggerMock
            ->expects($this->once())
            ->method('debug')
            ->with('123456789 -> Hello, World!');

        $debugConnector->send('123456789', 'Hello, World!');
    }

    public function testFailForThrowsAndRecordsNothing(): void
    {
        $debugConnector = new DebugConnector(
            $this->createStub(RequestStack::class),
            $this->createStub(LoggerInterface::class),
            [],
        );
        $debugConnector->failFor('421951111111');

        $caught = null;
        try {
            $debugConnector->send('421951111111', 'Hello');
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $caught, 'Expected the debug failure');
        $this->assertSame('Debug delivery failure for 421951111111', $caught->getMessage());
        $debugConnector->send('421951222222', 'Hello');
        $this->assertSame([['number' => '421951222222', 'text' => 'Hello']], $debugConnector->getSentMessages());

        $debugConnector->reset();
        $debugConnector->send('421951111111', 'Again');
        $this->assertSame([['number' => '421951111111', 'text' => 'Again']], $debugConnector->getSentMessages());
    }
}
