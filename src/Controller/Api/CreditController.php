<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\CreditRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CreditController extends AbstractController
{
    /**
     * @Route("/api/credit", name="api_credit_add", methods={"PUT"})
     */
    public function add(
        Request $request,
        CreditSystemInterface $creditSystem,
        CreditRepository $creditRepository,
        HistoryRepository $historyRepository,
        UserRepository $userRepository,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        $userId = $request->request->getInt('userId');
        $multiplier = $request->request->getInt('multiplier');

        if (
            empty($userId)
            || empty($multiplier)
            || !is_numeric($userId)
            || !is_numeric($multiplier)
            || $multiplier < 1
            || $multiplier > 10
        ) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $minRequiredCredit = $creditSystem->getMinRequiredCredit();
        $creditAmount = $minRequiredCredit * $multiplier;

        $creditRepository->addCredits($userId, (float)$creditAmount);
        $historyRepository->addItem(
            $userId,
            0, //BikeNum
            'CREDITCHANGE', //action
            $creditAmount . '|add+' . $creditAmount //parameter
        );

        $user = $userRepository->findItem($userId);

        return $this->json(
            [
                'message' => 'Added ' . $creditAmount . $creditSystem->getCreditCurrency() . ' credit for '
                    . $user['username'] . '.',
            ]
        );
    }
}
