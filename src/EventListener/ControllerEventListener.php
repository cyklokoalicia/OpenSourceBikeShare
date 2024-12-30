<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Db\DbInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Security;

class ControllerEventListener
{
    private const LOGGED_ROUTES = [
        'api_bike_index',
        'api_bike_item',
        'api_bike_last_usage',
        'api_coupon_index',
        'api_coupon_sell',
        'api_coupon_generate',
        'api_user_index',
        'api_user_item',
        'api_user_item_update',
        'api_credit_add',
    ];

    private DbInterface $db;
    private Security $security;

    public function __construct(
        DbInterface $db,
        Security $security
    ) {
        $this->db = $db;
        $this->security = $security;
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

        $this->db->query("
            INSERT INTO received 
                SET sms_uuid='', 
                    sender='$number',
                    receive_time='" . date('Y-m-d H:i:s') . "',
                    sms_text='" . $event->getRequest()->getRequestUri() . "',
                    ip='" . $event->getRequest()->getClientIp() . "'
        ");
        $this->db->commit();
    }
}
