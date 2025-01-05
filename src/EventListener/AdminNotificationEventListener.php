<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Db\DbInterface;
use BikeShare\Event\SmsDuplicateDetectedEvent;
use BikeShare\Event\SmsProcessedEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: SmsDuplicateDetectedEvent::NAME, method: 'onSmsDuplicateDetected')]
#[AsEventListener(event: SmsProcessedEvent::NAME, method: 'onSmsProcessed')]
class AdminNotificationEventListener
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

    public function onSmsDuplicateDetected(SmsDuplicateDetectedEvent $event): void
    {
        $this->sendNotification(
            $this->translator->trans('Problem with SMS') . $event->getSmsUuid(),
            false
        );
    }

    public function onSmsProcessed(SmsProcessedEvent $event)
    {
        switch ($event->getCommandName()) {
            case 'NOTE':
            case 'DELNOTE':
            case 'TAG':
            case 'UNTAG':
                $this->sendNotification(
                    $event->getUser()->getUsername() . ': ' . $event->getResultMessage(),
                    true,
                    [$event->getUser()->getUserId()]
                );
                break;
            default:
                break;
        }
    }

    private function sendNotification(string $message, bool $bySms = true, $excludedAdminIds = []): void
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
            $subject = $this->appName . ' ' . $this->translator->trans('notification');
            $this->mailer->sendMail($admin['mail'], $subject, $message);
        }
    }
}
