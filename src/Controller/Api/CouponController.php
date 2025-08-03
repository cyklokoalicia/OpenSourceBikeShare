<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Credit\CodeGenerator\CodeGeneratorInterface;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\CouponRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CouponController extends AbstractController
{
    public function __construct(
        private readonly CreditSystemInterface $creditSystem,
        private readonly CouponRepository $couponRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/coupon', name: 'api_coupon_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->creditSystem->isEnabled() === false) {
            $this->logger->notice('Credit system is disabled', [
                'user' => $this->getUser()->getUserIdentifier(),
            ]);

            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $coupons = $this->couponRepository->findAllActive();

        return $this->json($coupons);
    }

    #[Route('/api/coupon/sell', name: 'api_coupon_sell', methods: ['POST'])]
    public function sellCoupon(
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $coupon = $request->request->get('coupon');
        if ($this->creditSystem->isEnabled() === false) {
            $this->logger->notice('Credit system is disabled', [
                'user' => $this->getUser()->getUserIdentifier(),
            ]);

            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $this->couponRepository->updateStatus($coupon, 1); // Mark as sold

        return $this->json(
            [
                'message' => 'Coupon ' . $coupon . ' sold.',
            ]
        );
    }

    #[Route('/api/coupon/generate', name: 'api_coupon_generate', methods: ['POST'])]
    public function generate(
        Request $request,
        CodeGeneratorInterface $codeGenerator
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $multiplier = $request->request->get('multiplier');
        if (!is_numeric($multiplier) || $multiplier <= 0 || $multiplier > 5) {
            return $this->json(['message' => 'Invalid multiplier value', 'error' => 1], Response::HTTP_BAD_REQUEST);
        }

        $multiplier = (int) $multiplier;
        if ($this->creditSystem->isEnabled() === false) {
            $this->logger->notice('Credit system is disabled', [
                'user' => $this->getUser()->getUserIdentifier(),
            ]);

            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $minRequiredCredit = $this->creditSystem->getMinRequiredCredit();
        $value = $minRequiredCredit * $multiplier;
        $couponCodes = $codeGenerator->generate(10, 6);
        foreach ($couponCodes as $coupon) {
            $this->couponRepository->addItem($coupon, $value);
        }

        return $this->json(
            [
                'message' => 'Generated 10 new ' . $value . ' '
                    . $this->creditSystem->getCreditCurrency() . ' coupons.',
                'error' => 0,
            ]
        );
    }

    #[Route('/api/coupon/use', name: 'api_coupon_use', methods: ['POST'])]
    public function useCoupon(
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($this->creditSystem->isEnabled() === false) {
            $this->logger->notice('Credit system is disabled', [
                'user' => $this->getUser()->getUserIdentifier(),
            ]);

            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $coupon = $request->request->get('coupon', '');
        $couponData = $this->couponRepository->findActiveItem($coupon);
        if (is_null($couponData)) {
            return $this->json(
                [
                    'message' => 'Invalid coupon, try again.',
                    'error' => 1,
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->couponRepository->updateStatus($coupon, 2);// Mark as used
        $this->creditSystem->addCredit($this->getUser()->getUserId(), (float)$couponData['value'], $coupon);

        return $this->json(
            [
                'message' => '+' . $couponData['value'] . ' ' . $this->creditSystem->getCreditCurrency()
                    . '. Coupon ' . $coupon . ' has been redeemed.',
                'error' => 0,
            ]
        );
    }
}
