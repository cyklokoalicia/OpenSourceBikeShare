<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class CreditHistoryController extends AbstractController
{
    public function history(
        CreditSystemInterface $creditSystem,
        UserRepository $userRepository,
    ): Response {
        if (!$creditSystem->isEnabled()) {
            throw $this->createNotFoundException('Credit system is disabled');
        }

        $userId = (int)($userRepository->findItemByPhoneNumber($this->getUser()->getUserIdentifier())['userId']);
        $history = $creditSystem->getUserCreditHistory($userId);
        $currentCredit = $creditSystem->getUserCredit($userId);
        $currency = $creditSystem->getCreditCurrency();

        return $this->render('credit/history.html.twig', [
            'history' => $history,
            'currentCredit' => $currentCredit,
            'currency' => $currency,
        ]);
    }
}
