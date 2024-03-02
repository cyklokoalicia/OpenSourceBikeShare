<?php

namespace BikeShare\Mail;

class PHPMailerMailSender implements MailSenderInterface
{
    /**
     * @var string
     */
    private $fromEmail;
    /**
     * @var string
     */
    private $fromName;
    /**
     * @var array
     */
    private $emailConfig;
    /**
     * @var \PHPMailer
     */
    private $mailer;

    /**
     * @param string $fromEmail
     * @param string $fromName
     * @param array $emailConfig
     * @param \PHPMailer $mailer
     */
    public function __construct(
        $fromEmail,
        $fromName,
        array $emailConfig,
        \PHPMailer $mailer
    ) {
        #todo add validation of incoming params and throw exception if not valid
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->emailConfig = $emailConfig;
        $this->mailer = $mailer;
    }

    public function sendMail($recipient, $subject, $message)
    {
        $this->mailer->clearAllRecipients();

        $this->mailer->isSMTP(); // Set mailer to use SMTP
        //$this->mailer->SMTPDebug  = 2;
        $this->mailer->Host = $this->emailConfig["smtp"]; // Specify main and backup SMTP servers
        $this->mailer->Username = $this->emailConfig["user"]; // SMTP username
        $this->mailer->Password = $this->emailConfig["pass"]; // SMTP password
        $this->mailer->SMTPAuth = true; // Enable SMTP authentication
        $this->mailer->SMTPSecure = "ssl"; // Enable SSL
        $this->mailer->Port = 465; // TCP port to connect to
        $this->mailer->CharSet = "UTF-8";
        $this->mailer->From = $this->fromEmail;
        $this->mailer->FromName = $this->fromName;
        $this->mailer->addAddress($recipient);     // Add a recipient
        $this->mailer->addBCC($this->fromEmail);     // Add a recipient
        $this->mailer->Subject = $subject;
        $this->mailer->Body = $message;
        $this->mailer->send();
    }
}
