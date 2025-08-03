<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Credit\CreditSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(
        bool $isSmsSystemEnabled,
        CreditSystemInterface $creditSystem,
        ClockInterface $clock,
        LoggerInterface $logger,
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->redirectToRoute('login');
        }

        return $this->render(
            'admin/index.html.twig',
            [
                'isSmsSystemEnabled' => $isSmsSystemEnabled,
                'creditSystem' => $creditSystem,
                'currentYear' => $clock->now()->format('Y'),
            ]
        );
    }
}
