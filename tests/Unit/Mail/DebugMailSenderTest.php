<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Mail;

use BikeShare\Mail\DebugMailSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DebugMailSenderTest extends TestCase
{
    public function testSendMail()
    {
        $recipient = 'recipient';
        $subject = 'subject';
        $message = 'message';
        $loggerMock = $this->createMock(LoggerInterface::class);
        $mailer = new DebugMailSender($loggerMock);

        $loggerMock->expects($this->once())
            ->method('debug')
            ->with('Sending email', compact('recipient', 'subject', 'message'));

        $mailer->sendMail($recipient, $subject, $message);
    }

    public function testFailForThrowsAndRecordsNothing(): void
    {
        $mailer = new DebugMailSender($this->createStub(LoggerInterface::class));
        $mailer->failFor('a@x');

        try {
            $mailer->sendMail('a@x', 's', 'm');
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

        $mailer->sendMail('b@x', 's', 'm');

        $this->assertCount(1, $mailer->getSentMessages());
    }

    public function testResetClearsFailList(): void
    {
        $mailer = new DebugMailSender($this->createStub(LoggerInterface::class));
        $mailer->failFor('a@x');
        $mailer->reset();

        $mailer->sendMail('a@x', 's', 'm');

        $this->assertCount(1, $mailer->getSentMessages());
    }
}
