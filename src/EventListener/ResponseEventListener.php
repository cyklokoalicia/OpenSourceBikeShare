<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Db\DbInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Security;

class ResponseEventListener
{
    private const LOGGED_ROUTES = [
        'api_coupon_sell',
        'api_coupon_generate',
        'api_user_item_update',
        'api_credit_add',
        'api_bike_force_rent',
        'api_bike_force_return',
        'api_bike_rent',
        'api_bike_return',
        'api_bike_revert',
        'api_bike_remove_note',
        'api_coupon_use',
        'api_user_change_city',
        'api_stand_remove_note',
    ];

    public function __construct(
        private DbInterface $db,
        private Security $security,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!in_array($event->getRequest()->attributes->get('_route'), self::LOGGED_ROUTES)) {
            return;
        }

        $user = $this->security->getUser();
        $number = is_object($user) ? $user->getUserIdentifier() : 'guest';
        if ($event->getResponse() instanceof JsonResponse) {
            $response = json_decode($event->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['message'];
        } else {
            $response = $event->getResponse()->getContent();
        }

        $this->db->query(
            'INSERT INTO sent 
             SET number = :number,
                 text = :text',
            [
                'number' => $number,
                'text' => strip_tags((string)$response)
            ]
        );
    }
}
