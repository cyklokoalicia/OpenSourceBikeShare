<?php

namespace Test\BikeShare\Mail;

use BikeShare\Mail\DebugMailSender;
use PHPUnit\Framework\TestCase;

class DebugMailSenderTest extends TestCase
{
    public function testSendMail()
    {
        $recipient = 'recipient';
        $subject = 'subject';
        $message = 'message';
        $mailer = new DebugMailSender();
        $mailer->sendMail($recipient, $subject, $message);
        $this->expectOutputString($recipient . ' | ' . $subject . ' | ' . $message . PHP_EOL);
    }
}
