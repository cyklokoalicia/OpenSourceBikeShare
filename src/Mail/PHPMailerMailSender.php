<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

class PHPMailerMailSender implements MailSenderInterface
{
    public function __construct(
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly array $emailConfig,
        private readonly PHPMailer $mailer,
        private readonly int $debugLevel = 0,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function sendMail($recipient, $subject, $message)
    {
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
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $message;

        $this->mailer->send();

        $this->logger?->info('Email sent', [
            'recipient' => $recipient,
            'subject' => $subject,
        ]);
    }
}
