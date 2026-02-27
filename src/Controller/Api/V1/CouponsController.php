<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\CouponRepository;
use BikeShare\Enum\CreditChangeType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CouponsController extends AbstractController
{
    public function __construct(
        private readonly CreditSystemInterface $creditSystem,
        private readonly CouponRepository $couponRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function useCoupon(
        Request $request
    ): Response {
        if ($this->creditSystem->isEnabled() === false) {
            return $this->json(['detail' => 'Credit system is disabled'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getPayload()->all();
        $coupon = is_string($payload['coupon'] ?? null) ? $payload['coupon'] : '';
        $couponData = $this->couponRepository->findActiveItem($coupon);
        if (is_null($couponData)) {
            return $this->json(
                [
                    'detail' => 'Invalid coupon, try again.',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->couponRepository->updateStatus($coupon, 2);// Mark as used
        $this->creditSystem->increaseCredit(
            $this->getUser()->getUserId(),
            (float)$couponData['value'],
            CreditChangeType::COUPON_REDEMPTION,
            ['couponCode' => $coupon]
        );

        return $this->json(
            [
                'message' => '+' . $couponData['value'] . ' ' . $this->creditSystem->getCreditCurrency()
                    . '. Coupon ' . $coupon . ' has been redeemed.',
                'error' => 0,
            ]
        );
    }
}
