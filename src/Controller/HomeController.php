<?php

namespace BikeShare\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(
        Request $request,
        \BikeShare\Authentication\Auth $auth,
        \BikeShare\User\User $user,
        \BikeShare\Credit\CreditSystemInterface $creditSystem,
        \BikeShare\App\Configuration $configuration
    ): Response {
        return $this->render('index.html.twig', [
            'configuration' => $configuration,
            'auth' => $auth,
            'user' => $user,
            'creditSystem' => $creditSystem,
            'isSmsSystemEnabled' => $configuration->get('connectors')['sms'] == '',
            'error' => $request->get('error', null),
        ]);
    }
}
