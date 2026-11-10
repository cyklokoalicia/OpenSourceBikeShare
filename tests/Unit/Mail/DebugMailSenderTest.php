<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Mail;

use BikeShare\Mail\DebugMailSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatableMessage;

class DebugMailSenderTest extends TestCase
{
    public function testSendMail(): void
    {
        $recipient = 'a@x';
        $subject = $this->subject();
        $body = $this->body();
        $loggerMock = $this->createMock(LoggerInterface::class);
        $mailer = new DebugMailSender($loggerMock);

        $loggerMock->expects($this->once())
            ->method('debug')
            ->with('Sending email', ['recipient' => 'a@x', 'locale' => 'sk']);

        $mailer->sendMail($recipient, $subject, $body, 'sk');

        $sent = $mailer->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('a@x', $sent[0]['recipient']);
        $this->assertSame($subject, $sent[0]['subject']);
        $this->assertSame($body, $sent[0]['message']);
        $this->assertSame('sk', $sent[0]['locale']);
    }

    public function testFailForThrowsAndRecordsNothing(): void
    {
        $mailer = new DebugMailSender($this->createStub(LoggerInterface::class));
        $mailer->failFor('a@x');

        try {
            $mailer->sendMail('a@x', $this->subject(), $this->body(), 'sk');
            $this->fail('Expected a RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('Debug delivery failure for a@x', $e->getMessage());
        }

        $this->assertSame([], $mailer->getSentMessages());
    }

    public function testFailForDoesNotAffectOthers(): void
    {
        $mailer = new DebugMailSender($this->createStub(LoggerInterface::class));
        $mailer->failFor('a@x');

        $mailer->sendMail('b@x', $this->subject(), $this->body(), 'sk');

        $this->assertCount(1, $mailer->getSentMessages());
    }

    public function testResetClearsFailList(): void
    {
        $mailer = new DebugMailSender($this->createStub(LoggerInterface::class));
        $mailer->failFor('a@x');
        $mailer->reset();

        $mailer->sendMail('a@x', $this->subject(), $this->body(), 'sk');

        $this->assertCount(1, $mailer->getSentMessages());
    }

    private function subject(): TranslatableMessage
    {
        return new TranslatableMessage('mail.subject');
    }

    private function body(): TranslatableMessage
    {
        return new TranslatableMessage('mail.body', ['name' => 'Jane']);
    }
}
