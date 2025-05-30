<?php

declare(strict_types=1);

namespace BikeShare\Notifier;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminNotifier
{
    private string $appName;
    private DbInterface $db;
    private MailSenderInterface $mailer;
    private SmsSenderInterface $smsSender;
    private TranslatorInterface $translator;

    public function __construct(
        string $appName,
        DbInterface $db,
        MailSenderInterface $mailer,
        SmsSenderInterface $smsSender,
        TranslatorInterface $translator
    ) {
        $this->appName = $appName;
        $this->db = $db;
        $this->mailer = $mailer;
        $this->smsSender = $smsSender;
        $this->translator = $translator;
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
