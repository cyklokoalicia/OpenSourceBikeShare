<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Mail;

use BikeShare\Mail\DebugMailSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

class DebugMailSenderTest extends TestCase
{
    public function testSendMail()
    {
        $recipient = 'a@x';
        $subject = 'Predmet';
        $message = 'Ahoj Jane';
        $loggerMock = $this->createMock(LoggerInterface::class);
        $mailer = new DebugMailSender($this->translator(), $loggerMock);

        $loggerMock->expects($this->once())
            ->method('debug')
            ->with('Sending email', compact('recipient', 'subject', 'message'));

        $mailer->sendMail($recipient, $this->subject(), $this->body(), 'sk');

        $this->assertSame(
            [['recipient' => 'a@x', 'subject' => 'Predmet', 'message' => 'Ahoj Jane']],
            $mailer->getSentMessages()
        );
    }

    public function testFailForThrowsAndRecordsNothing(): void
    {
        $mailer = new DebugMailSender($this->translator(), $this->createStub(LoggerInterface::class));
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
        $mailer = new DebugMailSender($this->translator(), $this->createStub(LoggerInterface::class));
        $mailer->failFor('a@x');

        $mailer->sendMail('b@x', $this->subject(), $this->body(), 'sk');

        $this->assertCount(1, $mailer->getSentMessages());
    }

    public function testResetClearsFailList(): void
    {
        $mailer = new DebugMailSender($this->translator(), $this->createStub(LoggerInterface::class));
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

    private function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource(
            'array',
            ['mail.subject' => 'Predmet', 'mail.body' => 'Ahoj {name}'],
            'sk',
            'messages+intl-icu'
        );

        return $translator;
    }
}
