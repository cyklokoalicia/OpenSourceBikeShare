<?php

namespace BikeShare\Controller;

use BikeShare\App\Configuration;
use BikeShare\Credit\CreditSystemInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(
        Request $request,
        CreditSystemInterface $creditSystem,
        Configuration $configuration
    ): Response {
        return $this->render('index.html.twig', [
            'configuration' => $configuration,
            'creditSystem' => $creditSystem,
            'error' => $request->get('error', null),
        ]);
    }
}
