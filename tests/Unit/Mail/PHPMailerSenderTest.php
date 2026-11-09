<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Mail;

use BikeShare\Mail\PHPMailerMailSender;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

class PHPMailerSenderTest extends TestCase
{
    private PHPMailer&MockObject $mailer;
    private PHPMailerMailSender $mailSender;

    protected function setUp(): void
    {
        $email = [
            'smtp_host' => 'smtp_host',
            'smtp_port' => 999,
            'smtp_user' => 'smtp_user',
            'smtp_password' => 'smtp_password',
        ];

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource(
            'array',
            ['mail.subject' => 'Predmet', 'mail.body' => 'Ahoj {name}'],
            'sk',
            'messages+intl-icu'
        );

        $this->mailer = $this->createMock(PHPMailer::class);
        $this->mailSender = new PHPMailerMailSender(
            'fromEmail',
            'fromName',
            $email,
            $this->mailer,
            $translator
        );
    }

    public function testSendMail()
    {
        $recipient = 'recipient';

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
            ->method('send');

        $this->mailSender->sendMail(
            $recipient,
            new TranslatableMessage('mail.subject'),
            new TranslatableMessage('mail.body', ['name' => 'Jane']),
            'sk'
        );

        $this->assertEquals($this->mailer->Host, 'smtp_host');
        $this->assertEquals($this->mailer->Username, 'smtp_user');
        $this->assertEquals($this->mailer->Password, 'smtp_password');
        $this->assertEquals($this->mailer->SMTPAuth, true);
        $this->assertEquals($this->mailer->SMTPSecure, 'ssl');
        $this->assertEquals($this->mailer->Port, 999);
        $this->assertEquals($this->mailer->CharSet, 'UTF-8');
        $this->assertEquals($this->mailer->From, 'fromEmail');
        $this->assertEquals($this->mailer->FromName, 'fromName');
        $this->assertEquals($this->mailer->Subject, 'Predmet');
        $this->assertEquals($this->mailer->Body, 'Ahoj Jane');
    }

    public function testSendFailurePropagatesAndIsNotLoggedAsSent(): void
    {
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new \PHPMailer\PHPMailer\Exception('smtp down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');

        $mailSender = new PHPMailerMailSender(
            'fromEmail',
            'fromName',
            ['smtp_host' => 'h', 'smtp_port' => 1, 'smtp_user' => 'u', 'smtp_password' => 'p'],
            $this->mailer,
            new Translator('en'),
            0,
            $logger,
        );

        $this->expectException(\PHPMailer\PHPMailer\Exception::class);
        $mailSender->sendMail('recipient', new TranslatableMessage('s'), new TranslatableMessage('b'), 'en');
    }
}
