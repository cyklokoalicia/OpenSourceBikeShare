<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\App\Configuration;
use BikeShare\Db\DbInterface;
use BikeShare\Event\LongRentEvent;
use BikeShare\Event\ManyRentEvent;
use BikeShare\Event\SmsDuplicateDetectedEvent;
use BikeShare\Event\SmsProcessedEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: SmsDuplicateDetectedEvent::NAME, method: 'onSmsDuplicateDetected')]
#[AsEventListener(event: SmsProcessedEvent::NAME, method: 'onSmsProcessed')]
#[AsEventListener(event: LongRentEvent::NAME, method: 'onLongRent')]
#[AsEventListener(event: ManyRentEvent::NAME, method: 'onManyRent')]
class AdminNotificationEventListener
{
    private string $appName;
    private Configuration $configuration;
    private DbInterface $db;
    private MailSenderInterface $mailer;
    private SmsSenderInterface $smsSender;
    private TranslatorInterface $translator;

    public function __construct(
        string $appName,
        Configuration $configuration,
        DbInterface $db,
        MailSenderInterface $mailer,
        SmsSenderInterface $smsSender,
        TranslatorInterface $translator
    ) {
        $this->appName = $appName;
        $this->configuration = $configuration;
        $this->db = $db;
        $this->mailer = $mailer;
        $this->smsSender = $smsSender;
        $this->translator = $translator;
    }

    public function onManyRent(ManyRentEvent $event): void
    {
        $message = $this->translator->trans(
            'Bike rental over limit in {hour} hours',
            ['hour' => $this->configuration->get('watches')['timetoomany']]
        );
        foreach ($event->getAbusers() as $abuser) {
            $message .= PHP_EOL . $this->translator->trans(
                '{userName} ({phone}) rented {count} bikes',
                ['userName' => $abuser['userName'], 'phone' => $abuser['userPhone'], 'count' => $abuser['rentCount']]
            );
        }

        $this->sendNotification($message);
    }

    public function onLongRent(LongRentEvent $event): void
    {
        $message = $this->translator->trans(
            'Bike rental exceed {hour} hours',
            ['hour' => $this->configuration->get('watches')['longrental']]
        );
        foreach ($event->getAbusers() as $abuser) {
            $message .= PHP_EOL . 'B' . $abuser['bikeNumber'] . ' ' . $abuser['userName'] . ' ' . $abuser['userPhone'];
        }

        $this->sendNotification($message);
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
