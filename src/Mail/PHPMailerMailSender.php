<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PHPMailerMailSender implements MailSenderInterface
{
    public function __construct(
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly array $emailConfig,
        private readonly PHPMailer $mailer,
        private readonly TranslatorInterface $translator,
        private readonly int $debugLevel = 0,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function sendMail(
        string $recipient,
        TranslatableInterface $subject,
        TranslatableInterface $body,
        ?string $locale = null,
    ): void {
        $renderedSubject = $subject->trans($this->translator, $locale);
        $renderedBody = $body->trans($this->translator, $locale);

        $this->mailer->clearAllRecipients();

        $this->mailer->isSMTP(); // Set mailer to use SMTP
        $this->mailer->SMTPDebug = $this->debugLevel;

        $this->mailer->Host = $this->emailConfig["smtp_host"]; // Specify main and backup SMTP servers
        $this->mailer->Port = $this->emailConfig["smtp_port"]; // TCP port to connect to
        $this->mailer->Username = $this->emailConfig["smtp_user"]; // SMTP username
        $this->mailer->Password = $this->emailConfig["smtp_password"]; // SMTP password
        $this->mailer->SMTPAuth = true; // Enable SMTP authentication
        $this->mailer->SMTPSecure = "ssl"; // Enable SSL
        $this->mailer->CharSet = "UTF-8";
        $this->mailer->From = $this->fromEmail;
        $this->mailer->FromName = $this->fromName;
        $this->mailer->addAddress($recipient);     // Add a recipient
        $this->mailer->Subject = $renderedSubject;
        $this->mailer->Body = $renderedBody;

        $this->mailer->send();

        $this->logger?->info('Email sent', [
            'recipient' => $recipient,
            'subject' => $renderedSubject,
        ]);
    }
}
