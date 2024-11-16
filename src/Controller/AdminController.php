<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Configuration;
use BikeShare\Credit\CreditSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin")
     * @Route("/admin.php", name="admin_old")
     */
    public function index(
        Request $request,
        Configuration $configuration,
        CreditSystemInterface $creditSystem,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getNumber(),
                    'ip' => $request->getClientIp(),
                ]
            );

            return $this->redirectToRoute('login');
        }

        return $this->render(
            'admin/index.html.twig',
            [
                'configuration' => $configuration,
                'creditSystem' => $creditSystem,
            ]
        );
    }
}
