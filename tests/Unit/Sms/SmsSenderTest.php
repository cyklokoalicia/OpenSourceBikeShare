<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Sms;

use BikeShare\Db\DbInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

class SmsSenderTest extends TestCase
{
    private SmsConnectorInterface $smsConnectorMock;
    private DbInterface $dbMock;
    private ClockInterface $clockMock;
    private SmsSender $smsSender;

    protected function setUp(): void
    {
        $this->smsConnectorMock = $this->createMock(SmsConnectorInterface::class);
        $this->dbMock = $this->createMock(DbInterface::class);
        $this->clockMock = new MockClock();
        $this->smsSender = new SmsSender($this->smsConnectorMock, $this->dbMock, $this->clockMock);
    }

    protected function tearDown(): void
    {
        unset(
            $this->smsConnectorMock,
            $this->dbMock,
            $this->smsSender,
            $this->clockMock,
        );
    }

    /**
     * @dataProvider sendDataProvider
     */
    public function testSend(
        $number,
        $message,
        $currentDateTime,
        $smsConnectorCallParams,
        $smsConnectorMaxMessageLength,
        $dbEscapeCallParams,
        $dbEscapeCallResult,
        $dbCallParams
    ) {
        $this->clockMock->modify($currentDateTime);
        $matcher = $this->exactly(count($smsConnectorCallParams));
        $this->smsConnectorMock
            ->expects($matcher)
            ->method('send')
            ->willReturnCallback(function (...$parameters) use ($matcher, $smsConnectorCallParams) {
                $this->assertSame($smsConnectorCallParams[$matcher->getInvocationCount() - 1], $parameters);
            });
        $this->smsConnectorMock
            ->expects($this->once())
            ->method('getMaxMessageLength')
            ->willReturn($smsConnectorMaxMessageLength);
        $matcher = $this->exactly(count($dbEscapeCallParams));
        $this->dbMock
            ->expects($matcher)
            ->method('escape')
            ->willReturnCallback(function (...$parameters) use ($matcher, $dbEscapeCallParams, $dbEscapeCallResult) {
                $this->assertSame($dbEscapeCallParams[$matcher->getInvocationCount() - 1], $parameters);

                return $dbEscapeCallResult[$matcher->getInvocationCount() - 1];
            });
        $matcher = $this->exactly(count($dbCallParams));
        $this->dbMock
            ->expects($matcher)
            ->method('query')
            ->willReturnCallback(function (...$parameters) use ($matcher, $dbCallParams) {
                $this->assertSame($dbCallParams[$matcher->getInvocationCount() - 1], $parameters);
            });

        $this->smsSender->send($number, $message);
    }

    public function sendDataProvider()
    {
        yield 'short message' => [
            'number' => '123456789',
            'message' => 'Hello, World!',
            'currentDateTime' => '2023-10-01 12:00:00',
            'smsConnectorCallParams' => [
                ['123456789', 'Hello, World!']
            ],
            'smsConnectorMaxMessageLength' => 160,
            'dbEscapeCallParams' => [['Hello, World!']],
            'dbEscapeCallResult' => ['Hello, World!'],
            'dbCallParams' => [
                [
                    "INSERT INTO sent SET number = :number, text = :message, time = :time",
                    ['number' => '123456789', 'message' => 'Hello, World!', 'time' => '2023-10-01 12:00:00']
                ]
            ]
        ];
        yield 'encoded message' => [
            'number' => '123456789',
            'message' => 'Hello, "World"!',
            'currentDateTime' => '2023-12-01 12:00:00',
            'smsConnectorCallParams' => [
                ['123456789', 'Hello, "World"!']
            ],
            'smsConnectorMaxMessageLength' => 160,
            'dbEscapeCallParams' => [['Hello, "World"!']],
            'dbEscapeCallResult' => ['Hello, \"World\"!'],
            'dbCallParams' => [
                [
                    "INSERT INTO sent SET number = :number, text = :message, time = :time",
                    ['number' => '123456789', 'message' => 'Hello, \"World\"!', 'time' => '2023-12-01 12:00:00']
                ]
            ]
        ];
        yield 'long message' => [
            'number' => '123456789',
            'message' => 'Hello, World! Lorem ipsum dolor sit amet',
            'currentDateTime' => '2024-10-01 12:00:00',
            'smsConnectorCallParams' => [
                [
                    '123456789',
                    'Hello, World! Lorem'
                ],
                [
                    '123456789',
                    'ipsum dolor sit amet'
                ]
            ],
            'smsConnectorMaxMessageLength' => 20,
            'dbEscapeCallParams' => [
                [
                    'Hello, World! Lorem'
                ],
                [
                    'ipsum dolor sit amet'
                ]
            ],
            'dbEscapeCallResult' => [
                'Hello, World! Lorem ',
                'ipsum dolor sit amet',
            ],
            'dbCallParams' => [
                [
                    "INSERT INTO sent SET number = :number, text = :message, time = :time",
                    [
                        'number' => '123456789',
                        'message' => 'Hello, World! Lorem ',
                        'time' => '2024-10-01 12:00:00'
                    ]
                ],
                [
                    "INSERT INTO sent SET number = :number, text = :message, time = :time",
                    [
                        'number' => '123456789',
                        'message' => 'ipsum dolor sit amet',
                        'time' => '2024-10-01 12:00:00'
                    ]
                ],
            ]
        ];
    }
}
