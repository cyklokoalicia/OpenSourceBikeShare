<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\CityRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UsersController extends AbstractController
{
    public function __construct(
        private readonly int $freeTimeMinutes = 30,
    ) {
    }

    public function changeCity(
        UserRepository $userRepository,
        CityRepository $cityRepository,
        Request $request
    ): Response {
        $userId = $this->getUser()->getUserId();
        $payload = $request->getPayload()->all();
        $city = isset($payload['city']) && is_string($payload['city']) ? trim($payload['city']) : '';
        if (
            empty($city) ||
            !isset($cityRepository->findAvailableCities()[$city])
        ) {
            return $this->json(
                [
                    'detail' => 'Invalid city',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $userRepository->updateUserCity($userId, $city);

        return $this->json(
            [
                'message' => 'City changed successfully',
                'error' => 0,
            ]
        );
    }

    public function userBike(
        BikeRepository $bikeRepository
    ): Response {
        $userId = $this->getUser()->getUserId();

        $userBikes = $bikeRepository->findRentedBikesByUserId($userId);

        return $this->json($userBikes);
    }

    public function userLimit(
        BikeRepository $bikeRepository,
        UserRepository $userRepository,
        CreditSystemInterface $creditSystem
    ): Response {
        $userId = $this->getUser()->getUserId();
        $userBikes = $bikeRepository->findRentedBikesByUserId($userId);
        $userInfo = $userRepository->findItem($userId);
        $userCredit = $creditSystem->getUserCredit($userId);

        $result = [
            'limit' => $userInfo['userLimit'] - count($userBikes),
            'rented' => count($userBikes),
            'userCredit' => $userCredit,
            'freeTimeMinutes' => $this->freeTimeMinutes,
        ];

        return $this->json($result);
    }

    public function creditHistory(CreditSystemInterface $creditSystem): Response
    {
        $userId = $this->getUser()->getUserId();
        $creditHistory = $creditSystem->getUserCreditHistory($userId);

        return $this->json($creditHistory);
    }

    public function trips(HistoryRepository $historyRepository): Response
    {
        $userId = $this->getUser()->getUserId();
        $trips = $historyRepository->findUserTripHistory($userId, 10);

        return $this->json($trips);
    }
}
