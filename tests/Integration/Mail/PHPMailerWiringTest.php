<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Mail;

use BikeShare\Mail\PHPMailerMailSender;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use PHPMailer\PHPMailer\PHPMailer;

class PHPMailerWiringTest extends BikeSharingKernelTestCase
{
    public function testMailerIsBuiltWithExceptions(): void
    {
        $sender = self::getContainer()->get(PHPMailerMailSender::class);

        $mailerProperty = new \ReflectionProperty(PHPMailerMailSender::class, 'mailer');
        $mailer = $mailerProperty->getValue($sender);
        $this->assertInstanceOf(PHPMailer::class, $mailer);

        $exceptionsProperty = new \ReflectionProperty(PHPMailer::class, 'exceptions');
        $this->assertTrue($exceptionsProperty->getValue($mailer));
    }
}
