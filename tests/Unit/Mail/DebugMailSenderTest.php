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
}
