<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class WebAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        // A newbie (phone not yet confirmed) is sent to the phone-confirmation page
        // instead of seeing a bare 403. Any other access denial falls through to the
        // framework default (returning null).
        $user = $this->security->getUser();
        if ($user instanceof User && !$user->isNumberConfirmed()) {
            return new RedirectResponse($this->urlGenerator->generate('user_confirm_phone'));
        }

        return null;
    }
}
