<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\UserRepository;
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
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

        $creditSystem->addCredit($userId, (float)$creditAmount);

        $user = $userRepository->findItem($userId);

        return $this->json(
            [
                'message' => 'Added ' . $creditAmount . $creditSystem->getCreditCurrency() . ' credit for '
                    . $user['username'] . '.',
            ]
        );
    }
}
