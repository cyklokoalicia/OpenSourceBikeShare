<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Notification;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Notification\EmailChannelSender;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

class EmailChannelSenderTest extends TestCase
{
    private MailSenderInterface&MockObject $mailSender;
    private EmailChannelSender $sender;

    protected function setUp(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $domain = 'messages+intl-icu';
        $translator->addResource(
            'array',
            ['notification.subject.stack-watch' => 'Bike rented out of stack order'],
            'en',
            $domain
        );
        $translator->addResource(
            'array',
            ['notification.subject.stack-watch' => 'Bicykel požičaný mimo poradia'],
            'sk',
            $domain
        );
        $this->mailSender = $this->createMock(MailSenderInterface::class);
        $this->sender = new EmailChannelSender($this->mailSender, $translator);
    }

    public function testChannelName(): void
    {
        $this->assertSame('email', EmailChannelSender::getChannelName());
        $this->mailSender->expects($this->never())->method('sendMail');
    }

    public function testSubjectComesFromTheTypeAndBodyIsTranslated(): void
    {
        $this->mailSender->expects($this->once())->method('sendMail')
            ->with('a@x', 'Bike rented out of stack order', 'hello body');

        $this->sender->send('a@x', $this->notification(new TranslatableMessage('hello body')), 'en');
    }

    public function testUsesGivenLocale(): void
    {
        $this->mailSender->expects($this->once())->method('sendMail')
            ->with('a@x', 'Bicykel požičaný mimo poradia', 'hello body');

        $this->sender->send('a@x', $this->notification(new TranslatableMessage('hello body')), 'sk');
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
        return new Notification(NotificationType::StackWatch, $body);
    }
}
