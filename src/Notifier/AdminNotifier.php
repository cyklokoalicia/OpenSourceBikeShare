<?php

declare(strict_types=1);

namespace BikeShare\Notifier;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminNotifier
{
    public function __construct(
        private readonly string $appName,
        private readonly DbInterface $db,
        private readonly MailSenderInterface $mailer,
        private readonly SmsSenderInterface $smsSender,
        private readonly TranslatorInterface $translator,
        private readonly UserSettingsRepository $userSettingsRepository,
    ) {
    }

    public function notify(
        TranslatableInterface $message,
        bool $bySms = true,
        array $excludedAdminIds = []
    ): void {
        $subjectMessage = new TranslatableMessage('admin.notification.subject', ['appName' => $this->appName]);

        $admins = $this->db
            ->query('SELECT userId, number,mail FROM users where privileges & 2 != 0')
            ->fetchAllAssoc();
        foreach ($admins as $admin) {
            if (in_array($admin['userId'], $excludedAdminIds)) {
                continue;
            }

            $locale = $this->userSettingsRepository->findByUserId((int) $admin['userId'])['locale'] ?? null;

            if ($bySms) {
                $this->smsSender->send($admin['number'], $message, $locale);
            }

            $this->mailer->sendMail(
                $admin['mail'],
                $subjectMessage->trans($this->translator, $locale),
                $message->trans($this->translator, $locale),
            );
        }
    }
}
