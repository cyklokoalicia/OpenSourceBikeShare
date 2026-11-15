<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Sms;

use BikeShare\Repository\SentRepository;
use BikeShare\Sms\SmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\SmsTextNormalizer\SmsTextNormalizerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsSenderTest extends TestCase
{
    private SmsConnectorInterface&MockObject $smsConnectorMock;
    private SmsTextNormalizerInterface&MockObject $smsTextNormalizerMock;
    private SentRepository&MockObject $sentRepositoryMock;
    private TranslatorInterface&MockObject $translatorMock;
    private SmsSender $smsSender;

    protected function setUp(): void
    {
        $this->smsConnectorMock = $this->createMock(SmsConnectorInterface::class);
        $this->smsTextNormalizerMock = $this->createMock(SmsTextNormalizerInterface::class);
        $this->sentRepositoryMock = $this->createMock(SentRepository::class);
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->smsSender = new SmsSender(
            $this->smsConnectorMock,
            $this->smsTextNormalizerMock,
            $this->sentRepositoryMock,
            $this->translatorMock,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->smsConnectorMock,
            $this->smsTextNormalizerMock,
            $this->sentRepositoryMock,
            $this->translatorMock,
            $this->smsSender,
        );
    }

    #[DataProvider('translatableMessageDataProvider')]
    public function testSendsAndLogsTheRenderedNormalizedText(
        string $key,
        array $params,
        ?string $locale,
        string $rendered,
    ): void {
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with($key, $params, null, $locale)
            ->willReturn($rendered);
        $this->smsTextNormalizerMock
            ->expects($this->once())
            ->method('normalize')
            ->with($rendered)
            ->willReturn('normalized');
        $this->sentRepositoryMock->expects($this->once())->method('add')->with('123', 'normalized');
        $this->smsConnectorMock->expects($this->once())->method('send')->with('123', 'normalized');

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

    public function testFailedSendIsStillLogged(): void
    {
        $this->translatorMock->expects($this->once())->method('trans')->willReturn('Hello');
        $this->smsTextNormalizerMock->expects($this->once())->method('normalize')->willReturn('Hello');
        $this->sentRepositoryMock->expects($this->once())->method('add')->with('123', 'Hello');
        $this->smsConnectorMock->expects($this->once())->method('send')
            ->willThrowException(new \RuntimeException('gateway down'));

        $this->expectExceptionMessage('gateway down');
        $this->smsSender->send('123', new TranslatableMessage('Hello'));
    }
}
