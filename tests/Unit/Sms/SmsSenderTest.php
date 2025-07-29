<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Sms;

use BikeShare\Db\DbInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use PHPUnit\Framework\TestCase;

class SmsSenderTest extends TestCase
{
    private SmsConnectorInterface $smsConnector;
    private DbInterface $db;
    private SmsSender $smsSender;

    protected function setUp(): void
    {
        $this->smsConnector = $this->createMock(SmsConnectorInterface::class);
        $this->db = $this->createMock(DbInterface::class);
        $this->smsSender = new SmsSender($this->smsConnector, $this->db);
    }

    protected function tearDown(): void
    {
        unset(
            $this->smsConnector,
            $this->db,
            $this->smsSender
        );
    }

    /**
     * @dataProvider sendDataProvider
     */
    public function testSend(
        $number,
        $message,
        $smsConnectorCallParams,
        $smsConnectorMaxMessageLength,
        $dbEscapeCallParams,
        $dbEscapeCallResult,
        $dbCallParams
    ) {
        $matcher = $this->exactly(count($smsConnectorCallParams));
        $this->smsConnector
            ->expects($matcher)
            ->method('send')
            ->willReturnCallback(function (...$parameters) use ($matcher, $smsConnectorCallParams) {
                $this->assertSame($smsConnectorCallParams[$matcher->getInvocationCount() - 1], $parameters);
            });
        $this->smsConnector
            ->expects($this->once())
            ->method('getMaxMessageLength')
            ->willReturn($smsConnectorMaxMessageLength);
        $matcher = $this->exactly(count($dbEscapeCallParams));
        $this->db
            ->expects($matcher)
            ->method('escape')
            ->willReturnCallback(function (...$parameters) use ($matcher, $dbEscapeCallParams, $dbEscapeCallResult) {
                $this->assertSame($dbEscapeCallParams[$matcher->getInvocationCount() - 1], $parameters);

                return $dbEscapeCallResult[$matcher->getInvocationCount() - 1];
            });
        $matcher = $this->exactly(count($dbCallParams));
        $this->db
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
            'smsConnectorCallParams' => [
                ['123456789', 'Hello, World!']
            ],
            'smsConnectorMaxMessageLength' => 160,
            'dbEscapeCallParams' => [['Hello, World!']],
            'dbEscapeCallResult' => ['Hello, World!'],
            'dbCallParams' => [
                [
                    "INSERT INTO sent SET number = :number, text = :message",
                    ['number' => '123456789', 'message' => 'Hello, World!']
                ]
            ]
        ];
        yield 'encoded message' => [
            'number' => '123456789',
            'message' => 'Hello, "World"!',
            'smsConnectorCallParams' => [
                ['123456789', 'Hello, "World"!']
            ],
            'smsConnectorMaxMessageLength' => 160,
            'dbEscapeCallParams' => [['Hello, "World"!']],
            'dbEscapeCallResult' => ['Hello, \"World\"!'],
            'dbCallParams' => [
                [
                    "INSERT INTO sent SET number = :number, text = :message",
                    ['number' => '123456789', 'message' => 'Hello, \"World\"!']
                ]
            ]
        ];
        yield 'long message' => [
            'number' => '123456789',
            'message' => 'Hello, World! Lorem ipsum dolor sit amet',
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
                    "INSERT INTO sent SET number = :number, text = :message",
                    [
                        'number' => '123456789',
                        'message' => 'Hello, World! Lorem ',
                    ]
                ],
                [
                    "INSERT INTO sent SET number = :number, text = :message",
                    [
                        'number' => '123456789',
                        'message' => 'ipsum dolor sit amet'
                    ]
                ],
            ]
        ];
    }
}
