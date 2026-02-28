<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PhoneConfirmedEventListener
{
    private const ALLOWED_ROUTES = [
        'api_v1_user_phone_confirm_request',
        'api_v1_user_phone_confirm_verify',
        'sms_request',
        'sms_request_old',
        'user_confirm_email',
        'user_confirm_phone',
        'logout',
        '_profiler',
        '_profiler_home',
        '_profiler_search',
        '_profiler_search_bar',
        '_profiler_search_results',
        '_profiler_router',
        '_profiler_exception',
        '_profiler_exception_css',
        '_wdt',  // Web Debug Toolbar
    ];

    public function __construct(
        private readonly bool $isSmsSystemEnabled,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$this->isSmsSystemEnabled) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        if ($user->isNumberConfirmed()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $route = $request->attributes->get('_route');

        if (in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        if (str_starts_with($path, '/api/v1')) {
            $event->setResponse(new JsonResponse(
                ['detail' => 'Phone number must be confirmed.'],
                JsonResponse::HTTP_FORBIDDEN
            ));
        } else {
            $redirectUrl = $this->urlGenerator->generate('user_confirm_phone');
            $event->setResponse(new RedirectResponse($redirectUrl));
        }
    }
}
