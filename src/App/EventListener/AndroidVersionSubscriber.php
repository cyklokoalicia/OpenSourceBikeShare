<?php

declare(strict_types=1);

namespace BikeShare\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use BikeShare\App\Entity\User;
use BikeShare\Repository\UserClientRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AndroidVersionSubscriber implements EventSubscriberInterface
{
    private const PLATFORM_ANDROID = 'android';

    // High-frequency endpoints (map polling, per-screen calls) that would otherwise
    // trigger a no-op throttled write on every request.
    private const SKIPPED_ROUTES = [
        'api_v1_stand_markers' => true,
        'api_v1_me_city' => true,
        'api_v1_me_bikes' => true,
        'api_v1_me_limits' => true,
    ];

    public function __construct(
        private readonly ClientVersionDetector $clientVersionDetector,
        private readonly UserClientRepository $userClientRepository,
        private readonly Security $security,
        private readonly bool $isAndroidAppEnabled,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$this->isAndroidAppEnabled) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return;
        }

        if (isset(self::SKIPPED_ROUTES[$request->attributes->get('_route')])) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $clientVersion = $this->clientVersionDetector->getClientVersion($request);
        if (!$this->clientVersionDetector->isParsedAndroidVersion($clientVersion)) {
            return;
        }

        $this->userClientRepository->recordSeen(
            $user->getUserId(),
            self::PLATFORM_ANDROID,
            $clientVersion,
        );
    }
}
