<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Sms;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Db\DbResultInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\SmsTextNormalizer\SmsTextNormalizerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsSenderTest extends TestCase
{
    private SmsConnectorInterface&MockObject $smsConnectorMock;
    private SmsTextNormalizerInterface&MockObject $smsTextNormalizerMock;
    private DbInterface&MockObject $dbMock;
    private MockClock $clockMock;
    private TranslatorInterface&MockObject $translatorMock;
    private SmsSender $smsSender;

    protected function setUp(): void
    {
        $this->smsConnectorMock = $this->createMock(SmsConnectorInterface::class);
        $this->smsTextNormalizerMock = $this->createMock(SmsTextNormalizerInterface::class);
        $this->dbMock = $this->createMock(DbInterface::class);
        $this->clockMock = new MockClock();
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->smsSender = new SmsSender(
            $this->smsConnectorMock,
            $this->smsTextNormalizerMock,
            $this->dbMock,
            $this->clockMock,
            $this->translatorMock,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->smsConnectorMock,
            $this->smsTextNormalizerMock,
            $this->dbMock,
            $this->smsSender,
            $this->clockMock,
            $this->translatorMock,
        );
    }

    #[DataProvider('translatableMessageDataProvider')]
    public function testSendRendersTranslatableMessage(
        string $key,
        array $params,
        ?string $locale,
        string $rendered,
    ): void {
        $this->clockMock->modify('2024-01-01 10:00:00');

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with($key, $params, null, $locale)
            ->willReturn($rendered);
        $this->smsTextNormalizerMock
            ->expects($this->once())
            ->method('normalize')
            ->with($rendered)
            ->willReturn($rendered);
        $this->smsConnectorMock->expects($this->once())->method('getMaxMessageLength')->willReturn(160);
        $this->smsConnectorMock->expects($this->once())->method('send')->with('123', $rendered);
        $this->dbMock->expects($this->once())->method('query')->willReturn($this->createStub(DbResultInterface::class));

        $this->smsSender->send('123', new TranslatableMessage($key, $params), $locale);
    }

    public static function translatableMessageDataProvider(): iterable
    {
        yield 'default locale' => [
            'key' => 'command.help.message',
            'params' => ['commands' => 'HELP'],
            'locale' => null,
            'rendered' => 'Translated message',
        ];
        yield 'explicit locale' => [
            'key' => 'bike.return.success',
            'params' => ['bikeNumber' => 42],
            'locale' => 'de',
            'rendered' => 'Fahrrad 42 zurückgegeben',
        ];
    }

    public function testFailedSendIsNotLogged(): void
    {
        $this->translatorMock->expects($this->once())->method('trans')->willReturn('Hello');
        $this->smsTextNormalizerMock->expects($this->once())->method('normalize')->willReturn('Hello');
        $this->smsConnectorMock->method('getMaxMessageLength')->willReturn(160);
        $this->smsConnectorMock->expects($this->once())->method('send')
            ->willThrowException(new \RuntimeException('gateway down'));
        $this->dbMock->expects($this->never())->method('query');

        $this->expectException(\RuntimeException::class);
        $this->smsSender->send('123', new TranslatableMessage('Hello'));
    }

    public function testMultipartStopsLoggingAtTheFailedPart(): void
    {
        $this->clockMock->modify('2024-01-01 10:00:00');
        $message = 'aaaaaaaaaabbbbbbbbbbccccc';
        $this->translatorMock->expects($this->once())->method('trans')->willReturn($message);
        $this->smsTextNormalizerMock->expects($this->once())->method('normalize')->willReturn($message);
        $this->smsConnectorMock->method('getMaxMessageLength')->willReturn(10);
        $sendCalls = $this->exactly(2);
        $this->smsConnectorMock->expects($sendCalls)->method('send')
            ->willReturnCallback(static function (string $number, string $text) use ($sendCalls): void {
                if ($sendCalls->numberOfInvocations() === 2) {
                    throw new \RuntimeException('gateway down');
                }
            });
        $this->dbMock->expects($this->once())->method('query')
            ->with(
                'INSERT INTO sent SET number = :number, text = :message, time = :time',
                ['number' => '123', 'message' => 'aaaaaaaaaa', 'time' => '2024-01-01 10:00:00']
            )
            ->willReturn($this->createStub(DbResultInterface::class));

        $caught = null;
        try {
            $this->smsSender->send('123', new TranslatableMessage($message));
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $caught, 'The failed part must propagate');
        $this->assertSame('gateway down', $caught->getMessage());
    }

    #[DataProvider('sendDataProvider')]
    public function testSend(
        $number,
        $message,
        $currentDateTime,
        $smsConnectorCallParams,
        $smsConnectorMaxMessageLength,
        $dbCallParams
    ) {
        $this->clockMock->modify($currentDateTime);
        $matcher = $this->exactly(count($smsConnectorCallParams));
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with($message, [], null, null)
            ->willReturn($message);
        $this->smsTextNormalizerMock
            ->expects($this->once())
            ->method('normalize')
            ->with($message)
            ->willReturn($message);
        $this->smsConnectorMock
            ->expects($matcher)
            ->method('send')
            ->willReturnCallback(function (...$parameters) use ($matcher, $smsConnectorCallParams) {
                $this->assertSame($smsConnectorCallParams[$matcher->numberOfInvocations() - 1], $parameters);
            });
        $this->smsConnectorMock
            ->expects($this->once())
            ->method('getMaxMessageLength')
            ->willReturn($smsConnectorMaxMessageLength);
        $matcher = $this->exactly(count($dbCallParams));
        $this->dbMock
            ->expects($matcher)
            ->method('query')
            ->willReturnCallback(function (...$parameters) use ($matcher, $dbCallParams) {
                $this->assertSame($dbCallParams[$matcher->numberOfInvocations() - 1], $parameters);

                return $this->createStub(DbResultInterface::class);
            });

        $this->smsSender->send($number, new TranslatableMessage($message));
    }

    public static function sendDataProvider()
    {
        yield 'short message' => [
            'number' => '123456789',
            'message' => 'Hello, World!',
            'currentDateTime' => '2023-10-01 12:00:00',
            'smsConnectorCallParams' => [
                ['123456789', 'Hello, World!']
            ],
            'smsConnectorMaxMessageLength' => 160,
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
            'dbCallParams' => [
                [
                    "INSERT INTO sent SET number = :number, text = :message, time = :time",
                    ['number' => '123456789', 'message' => 'Hello, "World"!', 'time' => '2023-12-01 12:00:00']
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
            'dbCallParams' => [
                [
                    "INSERT INTO sent SET number = :number, text = :message, time = :time",
                    [
                        'number' => '123456789',
                        'message' => 'Hello, World! Lorem',
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
