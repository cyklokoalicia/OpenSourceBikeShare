<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Db\DbInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Bundle\SecurityBundle\Security;

class ResponseEventListener
{
    private const LOGGED_ROUTES = [
        'api_v1_admin_coupon_sell',
        'api_v1_admin_coupon_generate',
        'api_v1_admin_user_item_update',
        'api_v1_admin_user_credit_add',
        'api_v1_admin_rentals_force',
        'api_v1_admin_returns_force',
        'api_v1_rentals',
        'api_v1_returns',
        'api_v1_admin_reverts',
        'api_v1_admin_bike_notes_delete',
        'api_v1_admin_bike_set_code',
        'api_v1_coupon_redeem',
        'api_v1_admin_stand_notes_delete',
    ];

    public function __construct(
        private readonly DbInterface $db,
        private readonly Security $security,
        private readonly ClockInterface $clock,
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
            $content = $event->getResponse()->getContent();
            try {
                $decoded = json_decode((string)$content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }

            $statusCode = $event->getResponse()->getStatusCode();
            if (is_array($decoded)) {
                if ($statusCode >= Response::HTTP_BAD_REQUEST) {
                    $response = (string)($decoded['detail'] ?? '');
                } else {
                    $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
                    $response = (string)($data['message'] ?? '');
                }
            } else {
                $response = (string)$content;
            }
        } else {
            $response = $event->getResponse()->getContent();
        }

        $this->db->query(
            'INSERT INTO sent 
             SET number = :number,
                 text = :text,
                 time = :time',
            [
                'number' => $number,
                'text' => strip_tags((string)$response),
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );
    }
}
