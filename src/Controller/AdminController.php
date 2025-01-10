<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Credit\CreditSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin")
     * @Route("/admin.php", name="admin_old")
     */
    public function index(
        bool $isSmsSystemEnabled,
        CreditSystemInterface $creditSystem,
        LoggerInterface $logger
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
                'currentYear' => date('Y'),
            ]
        );
    }
}
