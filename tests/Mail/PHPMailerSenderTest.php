<?php

namespace Test\BikeShare\Mail;

use BikeShare\Mail\PHPMailerMailSender;
use PHPUnit\Framework\TestCase;

class PHPMailerSenderTest extends TestCase
{
    /**
     * @var \PHPMailer|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mailer;
    /**
     * @var PHPMailerMailSender
     */
    private $mailSender;

    protected function setUp()
    {
        $email = [
            'smtp' => 'smtp',
            'user' => 'user',
            'pass' => 'pass',
        ];

        $this->mailer = $this->createMock(\PHPMailer::class);
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

        $this->assertEquals($this->mailer->Host, 'smtp');
        $this->assertEquals($this->mailer->Username, 'user');
        $this->assertEquals($this->mailer->Password, 'pass');
        $this->assertEquals($this->mailer->SMTPAuth, true);
        $this->assertEquals($this->mailer->SMTPSecure, 'ssl');
        $this->assertEquals($this->mailer->Port, 465);
        $this->assertEquals($this->mailer->CharSet, 'UTF-8');
        $this->assertEquals($this->mailer->From, 'fromEmail');
        $this->assertEquals($this->mailer->FromName, 'fromName');
        $this->assertEquals($this->mailer->Subject, $subject);
        $this->assertEquals($this->mailer->Body, $message);
    }
}
