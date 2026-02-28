<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Credit\CreditSystemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends AbstractController
{
    public function index(
        bool $isSmsSystemEnabled,
        CreditSystemInterface $creditSystem,
        ClockInterface $clock,
    ): Response {
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
