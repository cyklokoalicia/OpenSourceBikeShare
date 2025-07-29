<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Db\DbInterface;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsControllerEventListener
{
    public function __construct(
        private DbInterface $db,
        private SmsConnectorInterface $smsConnector,
        private AdminNotifier $adminNotifier,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (
            !$event->isMainRequest()
            || !in_array(
                $event->getRequest()->attributes->get('_route'),
                ['sms_request', 'sms_request_old']
            )
        ) {
            return;
        }

        //temp logger just for case if something will goes wrong
        $this->logger->info(
            'SMS received',
            ['request' => array_merge($event->getRequest()->request->all(), $event->getRequest()->query->all())]
        );
        $this->smsConnector->receive();

        $sms_uuid = $this->smsConnector->getUUID();
        $sender = $this->smsConnector->getNumber();
        $receive_time = $this->smsConnector->getTime();
        $sms_text = $this->smsConnector->getMessage();
        $ip = $this->smsConnector->getIPAddress();

        $result = $this->db->query('SELECT sms_uuid FROM received WHERE sms_uuid=:sms_uuid', compact('sms_uuid'));
        if ($result->rowCount() >= 1) {
            // sms already exists in DB, possible problem
            $this->logger->error("SMS already exists in DB", compact('sms_uuid'));
            $this->adminNotifier->notify(
                $this->translator->trans('Problem with SMS') . $sms_uuid,
                false
            );
        } else {
            $this->db->query(
                'INSERT INTO received SET 
                 sms_uuid = :sms_uuid,
                 sender = :sender,
                 receive_time = :receive_time,
                 sms_text = :sms_text,
                 ip = :ip',
                [
                    'sms_uuid' => $sms_uuid,
                    'sender' => $sender,
                    'receive_time' => $receive_time,
                    'sms_text' => $sms_text,
                    'ip' => $ip
                ]
            );
        }
    }
}
