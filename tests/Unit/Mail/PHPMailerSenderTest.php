<?php

declare(strict_types=1);

namespace Test\BikeShare\Unit\Mail;

use BikeShare\Mail\PHPMailerMailSender;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PHPMailerSenderTest extends TestCase
{
    /**
     * @var PHPMailer|MockObject
     */
    private $mailer;
    /**
     * @var PHPMailerMailSender
     */
    private $mailSender;

    protected function setUp(): void
    {
        $email = [
            'smtp_host' => 'smtp_host',
            'smtp_port' => 999,
            'smtp_user' => 'smtp_user',
            'smtp_password' => 'smtp_password',
        ];

        $this->mailer = $this->createMock(PHPMailer::class);
        $this->mailSender = new PHPMailerMailSender(
            'fromEmail',
            'fromName',
            $email,
            $this->mailer
        );
    }

    public function testSendMail()
    {
        $recipient = 'recipient';
        $subject = 'subject';
        $message = 'message';

        $this->mailer
            ->expects($this->once())
            ->method('clearAllRecipients');
        $this->mailer
            ->expects($this->once())
            ->method('isSMTP');
        $this->mailer
            ->expects($this->once())
            ->method('addAddress')
            ->with($recipient);
        $this->mailer
            ->expects($this->once())
            ->method('addBCC')
            ->with('fromEmail');
        $this->mailer
            ->expects($this->once())
            ->method('send');

        $this->mailSender->sendMail($recipient, $subject, $message);

        $this->assertEquals($this->mailer->Host, 'smtp_host');
        $this->assertEquals($this->mailer->Username, 'smtp_user');
        $this->assertEquals($this->mailer->Password, 'smtp_password');
        $this->assertEquals($this->mailer->SMTPAuth, true);
        $this->assertEquals($this->mailer->SMTPSecure, 'ssl');
        $this->assertEquals($this->mailer->Port, 999);
        $this->assertEquals($this->mailer->CharSet, 'UTF-8');
        $this->assertEquals($this->mailer->From, 'fromEmail');
        $this->assertEquals($this->mailer->FromName, 'fromName');
        $this->assertEquals($this->mailer->Subject, $subject);
        $this->assertEquals($this->mailer->Body, $message);
    }
}
