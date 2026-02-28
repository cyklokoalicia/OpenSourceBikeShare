<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Db\DbInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Bundle\SecurityBundle\Security;

class ControllerEventListener
{
    private const LOGGED_ROUTES = [
        'api_v1_stands',
        'api_v1_stand_bikes',
        'api_v1_admin_stand_item',
        'api_v1_admin_stand_notes_delete',
        'api_v1_admin_bikes',
        'api_v1_bike_item',
        'api_v1_bike_last_usage',
        'api_v1_admin_coupons',
        'api_v1_admin_coupon_sell',
        'api_v1_admin_coupon_generate',
        'api_v1_admin_users',
        'api_v1_admin_user_item',
        'api_v1_admin_user_item_update',
        'api_v1_admin_user_credit_add',
        'api_v1_admin_report_daily',
        'api_v1_admin_report_users',
        'api_v1_admin_report_inactive_bikes',
        'api_v1_admin_rentals_force',
        'api_v1_admin_returns_force',
        'api_v1_rentals',
        'api_v1_returns',
        'api_v1_admin_reverts',
        'api_v1_admin_bike_notes_delete',
        'api_v1_admin_bike_set_code',
        'api_v1_coupon_redeem',
        'api_v1_bike_trip',
        // Scan routes used by the public flow.
        'scan_bike',
        'scan_stand',
    ];

    public function __construct(
        private readonly DbInterface $db,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!in_array($event->getRequest()->attributes->get('_route'), self::LOGGED_ROUTES)) {
            return;
        }

        $number = $this->security->getUser()->getUserIdentifier();

        $this->db->query(
            'INSERT INTO received 
                SET sms_uuid = :uuid, 
                    sender = :number,
                    receive_time = :receive_time,
                    sms_text = :sms_text,
                    ip = :ip',
            [
                'uuid' => '',
                'number' => $number,
                'receive_time' => $this->clock->now()->format('Y-m-d H:i:s'),
                'sms_text' => $event->getRequest()->getRequestUri(),
                'ip' => $event->getRequest()->getClientIp(),
            ]
        );
    }
}
