<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class ApiAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    private TokenStorageInterface $tokenStorage;
    private LoggerInterface $logger;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;

        $username = is_object($user) ? $user->getUserIdentifier() : 'guest';
        $this->logger->info(
            'API access denied',
            [
                'username' => $username,
                'uri' => $request->getRequestUri(),
                'ip' => $request->getClientIp(),
            ]
        );

        return new JsonResponse(
            ['code' => Response::HTTP_FORBIDDEN, 'message' => 'Access denied'],
            Response::HTTP_FORBIDDEN
        );
    }
}
