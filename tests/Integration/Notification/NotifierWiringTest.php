<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Notification;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\Recipients;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\Translation\TranslatableMessage;

class NotifierWiringTest extends BikeSharingKernelTestCase
{
    public function testAdminNotificationReachesDebugSenders(): void
    {
        $container = self::getContainer();
        $mailSender = $container->get(MailSenderInterface::class);
        $smsSender = $container->get(DebugSmsSender::class);
        $mailSender->reset();
        $smsSender->reset();

        $expected = [];
        foreach ($container->get(UserRepository::class)->findAdmins() as $admin) {
            if (!empty($admin['mail'])) {
                $expected[] = $admin['mail'];
            }
        }
        $this->assertNotEmpty($expected);

        $container->get(NotifierInterface::class)->notify(
            new Notification(
                NotificationType::InactiveBikes,
                new TranslatableMessage('admin.notification.inactive_bikes', ['lines' => 'x'])
            ),
            Recipients::admins()
        );

        $recipients = array_column($mailSender->getSentMessages(), 'recipient');
        $this->assertEqualsCanonicalizing($expected, $recipients);
        $this->assertSame([], $smsSender->getSentMessages());
    }
}
