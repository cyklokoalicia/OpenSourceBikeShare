<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Notification;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Notification\EmailChannelSender;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class EmailChannelSenderTest extends TestCase
{
    private MailSenderInterface&MockObject $mailSender;
    private EmailChannelSender $sender;

    protected function setUp(): void
    {
        $this->mailSender = $this->createMock(MailSenderInterface::class);
        $this->sender = new EmailChannelSender($this->mailSender);
    }

    public function testChannelName(): void
    {
        $this->assertSame('email', EmailChannelSender::getChannelName());
        $this->mailSender->expects($this->never())->method('sendMail');
    }

    public function testPassesTranslatablesAndLocaleToTransport(): void
    {
        $notification = $this->notification(new TranslatableMessage('hello body'));
        $this->mailSender->expects($this->once())->method('sendMail')
            ->with(
                'a@x',
                $this->identicalTo($notification->subject),
                $this->identicalTo($notification->body),
                'sk'
            );

        $this->sender->send('a@x', $notification, 'sk');
    }

    public function testExceptionPropagates(): void
    {
        $this->mailSender->expects($this->once())->method('sendMail')
            ->willThrowException(new \RuntimeException('smtp down'));

        $this->expectException(\RuntimeException::class);
        $this->sender->send('a@x', $this->notification(new TranslatableMessage('b')), 'en');
    }

    private function notification(TranslatableMessage $body): Notification
    {
        return new Notification(
            NotificationType::StackWatch,
            new TranslatableMessage('notification.subject.stack_watch'),
            $body
        );
    }
}
