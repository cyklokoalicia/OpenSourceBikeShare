<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\SmsProcessedEvent;
use BikeShare\Notifier\AdminNotifier;
use Symfony\Component\Translation\TranslatableMessage;

class SmsProcessedEventListener
{
    public function __construct(private readonly AdminNotifier $adminNotifier)
    {
    }

    public function __invoke(SmsProcessedEvent $event): void
    {
        switch ($event->getCommandName()) {
            case 'NOTE':
            case 'DELNOTE':
            case 'TAG':
            case 'UNTAG':
                $this->adminNotifier->notify(
                    new TranslatableMessage(
                        'admin.notification.sms_processed',
                        [
                            'userName' => $event->getUser()->getUsername(),
                            'message' => $event->getResultMessage(),
                        ]
                    ),
                    true,
                    [$event->getUser()->getUserId()]
                );
                break;
            default:
                break;
        }
    }
}
