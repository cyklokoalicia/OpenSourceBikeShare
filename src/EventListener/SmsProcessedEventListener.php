<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\SmsProcessedEvent;
use BikeShare\Notifier\AdminNotifier;

class SmsProcessedEventListener
{
    private AdminNotifier $adminNotifier;

    public function __construct(
        AdminNotifier $adminNotifier
    ) {
        $this->adminNotifier = $adminNotifier;
    }

    public function __invoke(SmsProcessedEvent $event)
    {
        switch ($event->getCommandName()) {
            case 'NOTE':
            case 'DELNOTE':
            case 'TAG':
            case 'UNTAG':
                $this->adminNotifier->notify(
                    $event->getUser()->getUsername() . ': ' . $event->getResultMessage(),
                    true,
                    [$event->getUser()->getUserId()]
                );
                break;
            default:
                break;
        }
    }
}
