<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Sms;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\SmsTextNormalizer\SmsTextNormalizerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsSenderTest extends TestCase
{
    private SmsConnectorInterface&MockObject $smsConnectorMock;
    private SmsTextNormalizerInterface&MockObject $smsTextNormalizerMock;
    private DbInterface&MockObject $dbMock;
    private ClockInterface $clockMock;
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
        $this->dbMock->expects($this->once())->method('query');

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
