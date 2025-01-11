<?php

declare(strict_types=1);

namespace BikeShare\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

class PHPMailerMailSender implements MailSenderInterface
{
    private string $fromEmail;
    private string $fromName;
    private array $emailConfig;
    private PHPMailer $mailer;
    private int $debugLevel;
    private ?LoggerInterface $logger;

    public function __construct(
        string $fromEmail,
        string $fromName,
        array $emailConfig,
        PHPMailer $mailer,
        int $debugLevel = 0,
        ?LoggerInterface $logger = null
    ) {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->emailConfig = $emailConfig;
        $this->mailer = $mailer;
        $this->debugLevel = $debugLevel;
        $this->logger = $logger;
    }

    public function sendMail($recipient, $subject, $message)
    {
        $this->mailer->clearAllRecipients();

        $this->mailer->isSMTP(); // Set mailer to use SMTP
        $this->mailer->SMTPDebug  = $this->debugLevel;
        if ($this->debugLevel > 0 && $this->logger) {
            $this->mailer->Debugoutput = [$this, "debugOutput"];
        }
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
        $this->mailer->addBCC($this->fromEmail);     // Add a recipient
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $message;
        $this->mailer->send();
    }

    /**
     * @internal
     */
    public function debugOutput($str, $level)
    {
        $this->logger->notice('PhpMailer Debug', ['output' => sprintf('[%s] %s', $level, $str)]);
    }
}
