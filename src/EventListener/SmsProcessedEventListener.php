<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\SmsProcessedEvent;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\Recipients;
use Symfony\Component\Translation\TranslatableMessage;

class SmsProcessedEventListener
{
    public function __construct(private readonly NotifierInterface $notifier)
    {
    }

    public function __invoke(SmsProcessedEvent $event): void
    {
        switch ($event->getCommandName()) {
            case 'NOTE':
            case 'DELNOTE':
            case 'TAG':
            case 'UNTAG':
                $this->notifier->notify(
                    new Notification(
                        NotificationType::SmsCommandAudit,
                        new TranslatableMessage(
                            'admin.notification.sms_processed',
                            [
                                'userName' => $event->getUser()->getUsername(),
                                'message' => $event->getResultMessage(),
                            ]
                        )
                    ),
                    Recipients::admins([$event->getUser()->getUserId()])
                );
                break;
            default:
                break;
        }
    }
}
