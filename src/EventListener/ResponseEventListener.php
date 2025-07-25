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

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        if (!in_array($event->getRequest()->attributes->get('_route'), self::LOGGED_ROUTES)) {
            return;
        }

        $number = $this->security->getUser()->getUserIdentifier();
        if ($event->getResponse() instanceof JsonResponse) {
            $response = json_decode($event->getResponse()->getContent(), true)['message'];
        } else {
            $response = $event->getResponse()->getContent();
        }

        $this->db->query(
            'INSERT INTO sent 
             SET number = :number,
                 text = :text',
            [
                'number' => $number,
                'text' => (string)$response
            ]
        );
    }
}
