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
use Symfony\Component\Routing\Annotation\Route;

class CouponController extends AbstractController
{
    private CreditSystemInterface $creditSystem;
    private CouponRepository $couponRepository;
    private LoggerInterface $logger;

    public function __construct(
        CreditSystemInterface $creditSystem,
        CouponRepository $couponRepository,
        LoggerInterface $logger
    ) {
        $this->creditSystem = $creditSystem;
        $this->couponRepository = $couponRepository;
        $this->logger = $logger;
    }

    /**
     * @Route("/api/coupon", name="api_coupon_index", methods={"GET"})
     */
    public function index(): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }
        if ($this->creditSystem->isEnabled() === false) {
            $this->logger->notice('Credit system is disabled', [
                'user' => $this->getUser()->getUserIdentifier(),
            ]);

            return new Response('', Response::HTTP_FORBIDDEN);
        }
        $coupons = $this->couponRepository->findAllActive();

        return $this->json($coupons);
    }

    /**
     * @Route("/api/coupon/sell", name="api_coupon_sell", methods={"POST"})
     */
    public function sell(
        Request $request
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }
        $coupon = $request->request->get('coupon');
        if ($this->creditSystem->isEnabled() === false) {
            $this->logger->notice('Credit system is disabled', [
                'user' => $this->getUser()->getUserIdentifier(),
            ]);

            return new Response('', Response::HTTP_FORBIDDEN);
        }
        $this->couponRepository->sell($coupon);

        return new Response('Coupon ' . $coupon . ' sold.');
    }

    /**
     * @Route("/api/coupon/generate", name="api_coupon_generate", methods={"POST"})
     */
    public function generate(
        Request $request,
        CodeGeneratorInterface $codeGenerator
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }
        $multiplier = $request->request->get('multiplier');
        if ($this->creditSystem->isEnabled() === false) {
            $this->logger->notice('Credit system is disabled', [
                'user' => $this->getUser()->getUserIdentifier(),
            ]);

            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $minRequiredCredit = $this->creditSystem->getMinRequiredCredit();
        $value = $minRequiredCredit * $multiplier;
        $couponCodes = $codeGenerator->generate(10, 6);
        foreach ($couponCodes as $coupon) {
            $this->couponRepository->addItem($coupon, $value);
        }

        return new Response(
            'Generated 10 new ' . $value . ' ' . $this->creditSystem->getCreditCurrency() . ' coupons.'
        );
    }
}
