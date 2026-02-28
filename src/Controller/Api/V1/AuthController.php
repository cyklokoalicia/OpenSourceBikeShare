<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\App\Security\JwtTokenService;
use BikeShare\App\Security\UserConfirmedEmailChecker;
use BikeShare\App\Security\UserProvider;
use BikeShare\Form\RegistrationFormType;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\CityRepository;
use BikeShare\Repository\RefreshTokenRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\User\UserRegistration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserProvider $userProvider,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JwtTokenService $jwtTokenService,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly UserConfirmedEmailChecker $userConfirmedEmailChecker,
        private readonly CityRepository $cityRepository,
        private readonly UserRegistration $userRegistration,
        private readonly PhonePurifierInterface $phonePurifier,
        private readonly bool $isSmsSystemEnabled = false,
    ) {
    }

    public function cities(): Response
    {
        $cities = array_keys($this->cityRepository->findAvailableCities());

        return $this->json($cities);
    }

    public function register(Request $request): Response
    {
        $payload = $request->getPayload()->all();
        $form = $this->createForm(RegistrationFormType::class);
        $form->submit($payload);

        if (!$form->isValid()) {
            $detail = $this->getFirstFormErrorMessage($form->getErrors(true));

            return $this->json(
                ['detail' => $detail],
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = $form->getData();
        $purifiedNumber = $this->phonePurifier->purify($data['number']);

        $this->userRegistration->register(
            $purifiedNumber,
            $data['useremail'],
            $data['password'],
            $data['city'],
            $data['fullname'],
            0
        );

        return $this->json(
            [
                'message' => 'You have been successfully registered. Please, check your email '
                    . 'and read the instructions to finish your registration.',
            ],
            Response::HTTP_CREATED
        );
    }

    private function getFirstFormErrorMessage(FormErrorIterator $errors): string
    {
        foreach ($errors as $error) {
            if ($error->getMessage() !== '') {
                return $error->getMessage();
            }
        }

        return 'Validation failed.';
    }

    public function token(Request $request): Response
    {
        $payload = $request->getPayload()->all();
        $number = is_string($payload['number'] ?? null) ? trim($payload['number']) : '';
        $password = is_string($payload['password'] ?? null) ? (string)$payload['password'] : '';

        if ($number === '' || $password === '') {
            return $this->json(['detail' => 'number and password are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($number);
        } catch (UserNotFoundException) {
            return $this->json(['detail' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['detail' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $confirmationError = $this->checkUserCanAuthenticate($user);
        if ($confirmationError !== null) {
            return $confirmationError;
        }

        $familyId = $this->generateFamilyId();
        $accessToken = $this->jwtTokenService->createAccessToken($user);
        $refreshToken = $this->jwtTokenService->createRefreshToken($user, $familyId);

        $this->refreshTokenRepository->store(
            $refreshToken['token'],
            $user->getUserId(),
            $familyId,
            $refreshToken['expiresAt'],
            $request->headers->get('User-Agent'),
            $request->getClientIp()
        );

        $phoneConfirmed = !$this->isSmsSystemEnabled || $user->isNumberConfirmed();

        return $this->json([
            'accessToken' => $accessToken['token'],
            'accessTokenExpiresAt' => $accessToken['expiresAt']->format(\DateTimeInterface::ATOM),
            'accessTokenExpiresIn' => $accessToken['expiresIn'],
            'refreshToken' => $refreshToken['token'],
            'refreshTokenExpiresAt' => $refreshToken['expiresAt']->format(\DateTimeInterface::ATOM),
            'refreshTokenExpiresIn' => $refreshToken['expiresIn'],
            'tokenType' => 'Bearer',
            'phoneConfirmed' => $phoneConfirmed,
        ]);
    }

    public function refresh(Request $request): Response
    {
        $payload = $request->getPayload()->all();
        $refreshToken = is_string($payload['refreshToken'] ?? null) ? trim($payload['refreshToken']) : '';

        if ($refreshToken === '') {
            return $this->json(['detail' => 'refreshToken is required'], Response::HTTP_BAD_REQUEST);
        }

        $storedToken = $this->refreshTokenRepository->findActiveByToken($refreshToken);
        if ($storedToken === null) {
            return $this->json(['detail' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $tokenPayload = $this->jwtTokenService->decodeAndValidate($refreshToken, 'refresh');
        } catch (\InvalidArgumentException) {
            return $this->json(['detail' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = isset($tokenPayload['sub']) ? (int)$tokenPayload['sub'] : 0;
        $familyId = isset($tokenPayload['family']) ? (string)$tokenPayload['family'] : '';
        if (
            $userId <= 0
            || $familyId === ''
            || (int)$storedToken['userId'] !== $userId
            || $storedToken['familyId'] !== $familyId
        ) {
            return $this->json(['detail' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $userData = $this->userRepository->findItem($userId);
        if ($userData === null) {
            return $this->json(['detail' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier((string)$userData['number']);
        } catch (UserNotFoundException) {
            return $this->json(['detail' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        $confirmationError = $this->checkUserCanAuthenticate($user);
        if ($confirmationError !== null) {
            return $confirmationError;
        }

        $accessToken = $this->jwtTokenService->createAccessToken($user);
        $newRefreshToken = $this->jwtTokenService->createRefreshToken($user, $familyId);

        $this->refreshTokenRepository->rotate(
            $refreshToken,
            $newRefreshToken['token'],
            $userId,
            $familyId,
            $newRefreshToken['expiresAt'],
            $request->headers->get('User-Agent'),
            $request->getClientIp(),
        );

        $phoneConfirmed = !$this->isSmsSystemEnabled || $user->isNumberConfirmed();

        return $this->json([
            'accessToken' => $accessToken['token'],
            'accessTokenExpiresAt' => $accessToken['expiresAt']->format(\DateTimeInterface::ATOM),
            'accessTokenExpiresIn' => $accessToken['expiresIn'],
            'refreshToken' => $newRefreshToken['token'],
            'refreshTokenExpiresAt' => $newRefreshToken['expiresAt']->format(\DateTimeInterface::ATOM),
            'refreshTokenExpiresIn' => $newRefreshToken['expiresIn'],
            'tokenType' => 'Bearer',
            'phoneConfirmed' => $phoneConfirmed,
        ]);
    }

    public function logout(Request $request): Response
    {
        $payload = $request->getPayload()->all();
        $refreshToken = is_string($payload['refreshToken'] ?? null) ? trim($payload['refreshToken']) : '';

        if ($refreshToken !== '') {
            $this->refreshTokenRepository->revokeToken($refreshToken);
            try {
                $tokenPayload = $this->jwtTokenService->decodeAndValidate($refreshToken, 'refresh');
                $familyId = isset($tokenPayload['family']) ? (string)$tokenPayload['family'] : '';
                if ($familyId !== '') {
                    $this->refreshTokenRepository->revokeFamily($familyId);
                }
            } catch (\InvalidArgumentException) {
                // Ignore malformed token on logout.
            }
        }

        return $this->json(['message' => 'Logged out']);
    }

    private function generateFamilyId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function checkUserCanAuthenticate(UserInterface $user): ?Response
    {
        try {
            $this->userConfirmedEmailChecker->checkPostAuth($user);
        } catch (AccountStatusException $e) {
            return $this->json(['detail' => $e->getMessageKey()], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
