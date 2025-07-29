<?php

declare(strict_types=1);

namespace BikeShare\Notifier;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminNotifier
{
    public function __construct(
        private readonly string $appName,
        private readonly DbInterface $db,
        private readonly MailSenderInterface $mailer,
        private readonly SmsSenderInterface $smsSender,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function notify(string $message, bool $bySms = true, $excludedAdminIds = []): void
    {
        $admins = $this->db
            ->query('SELECT userId, number,mail FROM users where privileges & 2 != 0')
            ->fetchAllAssoc();
        foreach ($admins as $admin) {
            if (in_array($admin['userId'], $excludedAdminIds)) {
                continue;
            }

            if ($bySms) {
                $this->smsSender->send($admin['number'], $message);
            }

            $subject = $this->translator->trans('{appName} notification', ['appName' => $this->appName]);
            $this->mailer->sendMail($admin['mail'], $subject, $message);
        }
    }
}
