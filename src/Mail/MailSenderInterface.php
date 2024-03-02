<?php

namespace BikeShare\Mail;

interface MailSenderInterface
{
    public function sendMail($recipient, $subject, $message);
}
