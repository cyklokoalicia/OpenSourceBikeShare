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
        $translator->addResource('array', ['admin.notification.subject' => '{appName} notification'], 'en', $domain);
        $translator->addResource('array', ['admin.notification.subject' => '{appName} oznámenie'], 'sk', $domain);
        $this->mailSender = $this->createMock(MailSenderInterface::class);
        $this->sender = new EmailChannelSender('WB', $this->mailSender, $translator);
    }

    public function testChannelName(): void
    {
        $this->assertSame('email', EmailChannelSender::getChannelName());
        $this->mailSender->expects($this->never())->method('sendMail');
    }

    public function testDefaultSubjectAndTranslatedBody(): void
    {
        $this->mailSender->expects($this->once())->method('sendMail')
            ->with('a@x', 'WB notification', 'hello body');

        $this->sender->send('a@x', $this->notification(new TranslatableMessage('hello body')), 'en');
    }

    public function testExplicitSubjectWins(): void
    {
        $this->mailSender->expects($this->once())->method('sendMail')
            ->with('a@x', 'Custom subject', 'hello body');

        $this->sender->send(
            'a@x',
            $this->notification(
                new TranslatableMessage('hello body'),
                new TranslatableMessage('Custom subject')
            ),
            'en'
        );
    }

    public function testUsesGivenLocale(): void
    {
        $this->mailSender->expects($this->once())->method('sendMail')
            ->with('a@x', 'WB oznámenie', 'hello body');

        $this->sender->send('a@x', $this->notification(new TranslatableMessage('hello body')), 'sk');
    }

    public function testExceptionPropagates(): void
    {
        $this->mailSender->expects($this->once())->method('sendMail')
            ->willThrowException(new \RuntimeException('smtp down'));

        $this->expectException(\RuntimeException::class);
        $this->sender->send('a@x', $this->notification(new TranslatableMessage('b')), 'en');
    }

    private function notification(
        TranslatableMessage $body,
        ?TranslatableMessage $subject = null
    ): Notification {
        return new Notification(NotificationType::cases()[0], $body, $subject);
    }
}
