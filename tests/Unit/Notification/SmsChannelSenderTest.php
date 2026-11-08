<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Notification;

use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\SmsChannelSender;
use BikeShare\Sms\SmsSenderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class SmsChannelSenderTest extends TestCase
{
    public function testChannelName(): void
    {
        $this->assertSame('sms', SmsChannelSender::getChannelName());
    }

    public function testPassesOriginalTranslatable(): void
    {
        $body = new TranslatableMessage('sms.body', ['x' => 1]);
        $smsSender = $this->createMock(SmsSenderInterface::class);
        $smsSender->expects($this->once())->method('send')
            ->with('421900', $this->identicalTo($body), 'sk');

        (new SmsChannelSender($smsSender))->send(
            '421900',
            new Notification(NotificationType::cases()[0], $body),
            'sk'
        );
    }

    public function testExceptionPropagates(): void
    {
        $smsSender = $this->createMock(SmsSenderInterface::class);
        $smsSender->expects($this->once())->method('send')->willThrowException(new \RuntimeException('gateway down'));

        $this->expectException(\RuntimeException::class);
        (new SmsChannelSender($smsSender))->send(
            '421900',
            new Notification(NotificationType::cases()[0], new TranslatableMessage('b')),
            null
        );
    }
}
