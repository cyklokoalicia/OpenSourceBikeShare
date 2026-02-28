<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Enum\Action;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\CityRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\SmsSenderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UsersController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly HistoryRepository $historyRepository,
        private readonly SmsSenderInterface $smsSender,
        private readonly int $freeTimeMinutes = 30,
        private readonly bool $isSmsSystemEnabled = false,
    ) {
    }

    public function changeCity(
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

        $this->userRepository->updateUserCity($userId, $city);

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
        CreditSystemInterface $creditSystem
    ): Response {
        $userId = $this->getUser()->getUserId();
        $userBikes = $bikeRepository->findRentedBikesByUserId($userId);
        $userInfo = $this->userRepository->findItem($userId);
        $userCredit = $creditSystem->getUserCredit($userId);

        $result = [
            'limit' => $userInfo['userLimit'] - count($userBikes),
            'rented' => count($userBikes),
            'userCredit' => $userCredit,
            'freeTimeMinutes' => $this->freeTimeMinutes,
            'privileges' => (int)($userInfo['privileges'] ?? 0),
        ];

        return $this->json($result);
    }

    public function creditHistory(CreditSystemInterface $creditSystem): Response
    {
        $userId = $this->getUser()->getUserId();
        $creditHistory = $creditSystem->getUserCreditHistory($userId);
        $data = array_map(static function (array $entry): array {
            return [
                'date' => $entry['date']->format(\DateTimeInterface::ATOM),
                'amount' => $entry['amount'],
                'type' => $entry['type'],
                'balance' => $entry['balance'],
            ];
        }, $creditHistory);

        return $this->json($data);
    }

    public function trips(): Response
    {
        $userId = $this->getUser()->getUserId();
        $trips = $this->historyRepository->findUserTripHistory($userId, 10);

        return $this->json($trips);
    }

    public function phoneConfirmRequest(): Response
    {
        if (!$this->isSmsSystemEnabled) {
            return $this->json(
                ['detail' => 'Phone verification is not available.'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $user = $this->getUser();
        if ($user->isNumberConfirmed()) {
            return $this->json(['message' => 'Phone number is already confirmed.']);
        }

        $number = $user->getNumber();
        $smsCode = chr(rand(65, 90)) . chr(rand(65, 90)) . ' ' . rand(100000, 999999);
        $sanitizedSmsCode = str_replace(' ', '', $smsCode);
        $checkCode = md5('WB' . $number . $sanitizedSmsCode);

        $text = 'Enter this code to verify your phone: ' . $smsCode;
        $this->smsSender->send($number, $text);
        $this->historyRepository->addItem(
            $user->getUserId(),
            0,
            Action::PHONE_CONFIRM_REQUEST,
            sprintf('%s;%s;%s', $number, $sanitizedSmsCode, $checkCode)
        );

        return $this->json(['checkCode' => $checkCode]);
    }

    public function phoneConfirmVerify(Request $request): Response
    {
        $user = $this->getUser();
        if ($user->isNumberConfirmed()) {
            return $this->json(['message' => 'Phone number is already confirmed.']);
        }

        $payload = $request->getPayload()->all();
        $code = isset($payload['code']) ? str_replace(' ', '', trim((string)$payload['code'])) : '';
        $checkCode = isset($payload['checkCode']) ? trim((string)$payload['checkCode']) : '';

        if ($code === '' || $checkCode === '') {
            return $this->json(
                ['detail' => 'code and checkCode are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $number = $user->getNumber();
        $parameter = $number . ';' . $code . ';' . $checkCode;
        $history = $this->historyRepository->findConfirmationRequest($parameter, $user->getUserId());

        if ($history === null) {
            return $this->json(['detail' => 'Invalid confirmation code.'], Response::HTTP_BAD_REQUEST);
        }

        $this->userRepository->confirmUserNumber($user->getUserId());
        $this->historyRepository->addItem($user->getUserId(), 0, Action::PHONE_CONFIRMED, '');

        return $this->json(['message' => 'Phone number confirmed successfully.']);
    }
}
