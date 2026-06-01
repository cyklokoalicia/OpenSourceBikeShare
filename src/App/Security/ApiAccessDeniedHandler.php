<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class ApiAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly LoggerInterface $logger
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

        if ($user instanceof User && !$user->isNumberConfirmed()) {
            return new JsonResponse(
                ['detail' => 'Phone number must be confirmed.', 'code' => 'phone_unconfirmed'],
                Response::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse(
            ['detail' => 'Access denied'],
            Response::HTTP_FORBIDDEN
        );
    }
}
