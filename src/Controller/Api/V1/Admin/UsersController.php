<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1\Admin;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Enum\CreditChangeType;
use BikeShare\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UsersController extends AbstractController
{
    public function index(
        UserRepository $userRepository
    ): Response {
        $users = $userRepository->findAll();

        return $this->json($users);
    }

    public function item(
        string $userId,
        UserRepository $userRepository
    ): Response {
        if (empty($userId) || !is_numeric($userId)) {
            return $this->json(['detail' => 'Invalid user id'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findItem((int)$userId);
        if ($user === null) {
            return $this->json(['detail' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($user);
    }

    public function update(
        string $userId,
        bool $isSmsSystemEnabled,
        Request $request,
        UserRepository $userRepository
    ): Response {
        if (empty($userId) || !is_numeric($userId)) {
            return $this->json(['detail' => 'Invalid user id'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getPayload()->all();
        $userName = isset($payload['username']) && is_string($payload['username']) ? trim($payload['username']) : '';
        $email = isset($payload['email']) && is_string($payload['email']) ? trim($payload['email']) : '';
        $number = isset($payload['number']) && is_string($payload['number']) ? trim($payload['number']) : '';
        $privileges = isset($payload['privileges']) && is_numeric($payload['privileges'])
            ? (int)$payload['privileges']
            : 0;
        $userLimit = isset($payload['userLimit']) && is_numeric($payload['userLimit'])
            ? (int)$payload['userLimit']
            : 0;

        if (
            empty($userName)
            || empty($email)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($isSmsSystemEnabled && empty($number))
        ) {
            return $this->json(['detail' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $userRepository->updateItem(
            (int)$userId,
            $userName,
            $email,
            $number,
            $privileges,
            $userLimit
        );

        return $this->json([
            'message' => 'Details of user ' . $userName . ' updated.'
        ]);
    }

    public function addCredit(
        string $userId,
        Request $request,
        CreditSystemInterface $creditSystem,
        UserRepository $userRepository
    ): Response {
        if (empty($userId) || !is_numeric($userId)) {
            return $this->json(['detail' => 'Invalid user id'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $request->getPayload()->all();
        $multiplier = isset($payload['multiplier']) && is_numeric($payload['multiplier'])
            ? (int)$payload['multiplier']
            : 0;
        if ($multiplier < 1 || $multiplier > 10) {
            return $this->json(['detail' => 'Invalid multiplier'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findItem((int)$userId);
        if ($user === null) {
            return $this->json(['detail' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $minRequiredCredit = $creditSystem->getMinRequiredCredit();
        $creditAmount = $minRequiredCredit * $multiplier;
        $creditSystem->increaseCredit((int)$userId, (float)$creditAmount, CreditChangeType::CREDIT_ADD);

        $username = isset($user['userName']) && is_string($user['userName']) ? $user['userName'] : ('#' . $userId);

        return $this->json([
            'message' => 'Added ' . $creditAmount . $creditSystem->getCreditCurrency() . ' credit for '
                . $username . '.',
        ]);
    }
}
