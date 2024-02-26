<?php

namespace BikeShare\Mail;

class DebugMailSender implements MailSenderInterface
{
    public function sendMail($recipient, $subject, $message)
    {
        echo $recipient, ' | ', $subject, ' | ', $message . PHP_EOL;
    }
}
