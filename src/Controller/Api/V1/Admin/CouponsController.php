<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1\Admin;

use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\CouponRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CouponsController extends AbstractController
{
    public function __construct(
        private readonly CreditSystemInterface $creditSystem,
        private readonly CouponRepository $couponRepository,
    ) {
    }

    public function index(): Response
    {
        if ($this->creditSystem->isEnabled() === false) {
            return $this->json(['detail' => 'Credit system is disabled'], Response::HTTP_BAD_REQUEST);
        }

        $coupons = $this->couponRepository->findAllActive();

        return $this->json($coupons);
    }

    public function sellCoupon(
        string $coupon,
    ): Response {
        if (trim($coupon) === '') {
            return $this->json(['detail' => 'coupon is required'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->creditSystem->isEnabled() === false) {
            return $this->json(['detail' => 'Credit system is disabled'], Response::HTTP_BAD_REQUEST);
        }

        $this->couponRepository->updateStatus($coupon, 1); // Mark as sold

        return $this->json([
            'message' => 'Coupon ' . $coupon . ' sold.',
        ]);
    }

    public function generate(
        Request $request,
        CodeGeneratorInterface $codeGenerator
    ): Response {
        $payload = $request->getPayload()->all();
        $multiplier = $payload['multiplier'] ?? null;
        if (!is_numeric($multiplier) || $multiplier <= 0 || $multiplier > 5) {
            return $this->json(['detail' => 'Invalid multiplier value'], Response::HTTP_BAD_REQUEST);
        }

        $multiplier = (int)$multiplier;
        if ($this->creditSystem->isEnabled() === false) {
            return $this->json(['detail' => 'Credit system is disabled'], Response::HTTP_BAD_REQUEST);
        }

        $minRequiredCredit = $this->creditSystem->getMinRequiredCredit();
        $value = $minRequiredCredit * $multiplier;
        $couponCodes = $codeGenerator->generate(10, 6);
        foreach ($couponCodes as $couponCode) {
            $this->couponRepository->addItem($couponCode, $value);
        }

        return $this->json([
            'message' => 'Generated 10 new ' . $value . ' '
                . $this->creditSystem->getCreditCurrency() . ' coupons.',
            'error' => 0,
        ]);
    }
}
