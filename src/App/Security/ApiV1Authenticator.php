<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class ApiV1Authenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService,
        private readonly UserRepository $userRepository,
        private readonly UserProvider $userProvider,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if (!str_starts_with($request->getPathInfo(), '/api/v1/')) {
            return false;
        }

        if (str_starts_with($request->getPathInfo(), '/api/v1/auth/')) {
            return false;
        }

        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        if (!is_string($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('No bearer token provided.');
        }

        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            throw new AuthenticationException('No bearer token provided.');
        }

        return $this->authenticateJwtUser($token);
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?JsonResponse {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(
            ['detail' => 'Invalid credentials'],
            JsonResponse::HTTP_UNAUTHORIZED
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        $response = new JsonResponse(
            ['detail' => 'Authentication required'],
            JsonResponse::HTTP_UNAUTHORIZED
        );
        $response->headers->set('WWW-Authenticate', 'Bearer');

        return $response;
    }

    private function authenticateJwtUser(string $token): Passport
    {
        try {
            $payload = $this->jwtTokenService->decodeAndValidate($token, 'access');
        } catch (\InvalidArgumentException $e) {
            throw new AuthenticationException('Invalid access token.', 0, $e);
        }

        $userId = isset($payload['sub']) ? (int)$payload['sub'] : 0;
        if ($userId <= 0) {
            throw new AuthenticationException('Invalid access token subject.');
        }

        $userData = $this->userRepository->findItem($userId);
        if ($userData === null || !isset($userData['number'])) {
            throw new AuthenticationException('User not found for access token.');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                (string)$userId,
                fn() => $this->userProvider->loadUserByIdentifier((string)$userData['number'])
            )
        );
    }
}
