<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Sms;

use BikeShare\Sms\DebugSmsSender;
use BikeShare\Sms\SmsSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatableMessage;

class DebugSmsSenderTest extends TestCase
{
    public function testSendRecordsLogsAndForwards(): void
    {
        $innerMock = $this->createMock(SmsSender::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $sender = new DebugSmsSender($innerMock, $loggerMock);

        $number = '421900111222';
        $message = new TranslatableMessage('bike.rent.success', ['bikeNumber' => 5]);
        $locale = 'en';

        $loggerMock->expects($this->once())
            ->method('debug')
            ->with('Sending sms', ['number' => $number, 'locale' => $locale]);
        $innerMock->expects($this->once())
            ->method('send')
            ->with($number, $message, $locale);

        $sender->send($number, $message, $locale);

        $this->assertSame(
            [['number' => $number, 'message' => $message, 'locale' => $locale]],
            $sender->getSentMessages()
        );
    }

    public function testResetClearsSentMessages(): void
    {
        $sender = new DebugSmsSender(
            $this->createStub(SmsSender::class),
            $this->createStub(LoggerInterface::class),
        );

        $sender->send('421900111222', new TranslatableMessage('foo'));
        $this->assertCount(1, $sender->getSentMessages());

        $sender->reset();
        $this->assertSame([], $sender->getSentMessages());
    }
}
