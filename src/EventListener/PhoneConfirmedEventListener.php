<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class PhoneConfirmedEventListener
{
    private const ALLOWED_ROUTES = [
        'command', //should be removed in future
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
        private bool $isSmsSystemEnabled,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
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

        if (in_array($event->getRequest()->attributes->get('_route'), self::ALLOWED_ROUTES)) {
            return;
        }

        if (!$user->isNumberConfirmed()) {
            $redirectUrl = $this->urlGenerator->generate('user_confirm_phone');
            $event->setResponse(new RedirectResponse($redirectUrl));
        }
    }
}
