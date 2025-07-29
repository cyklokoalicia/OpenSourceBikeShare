<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class ApiAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private Security $security,
        private LoggerInterface $logger
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        $user = $this->security->getUser();

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
