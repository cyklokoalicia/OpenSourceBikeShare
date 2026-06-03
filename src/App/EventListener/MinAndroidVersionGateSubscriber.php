<?php

declare(strict_types=1);

namespace BikeShare\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Force-update gate (spec 0005). The web is the single source of truth for the minimum
 * supported Android version; a client below it is rejected with `426 Upgrade Required`
 * before it reaches auth or any controller, so a too-old build cannot hit removed or
 * changed endpoints. The Android app reacts to the 426 by showing a blocking screen.
 *
 * Disabled when the floor is empty; the shipped `.env.dist` sets it to 1.1.0. (Empty is
 * the rollout escape hatch — ship off, then raise once a target version's adoption is high
 * enough. Note: with the gate off, pre-floor clients are re-admitted to the API and no
 * longer get compat transforms pruned under spec 0012, so only disable knowingly.)
 * Version detection is reused from
 * {@see ClientVersionDetector}: browsers/admin/curl map to "999.0.0" and so are never
 * below a real floor, while legacy okhttp Android (no custom UA) maps to "0.0.0" and is
 * blocked. A plain `version_compare` against the floor therefore gates exactly the
 * Android clients we mean to and nothing else — no special-casing needed.
 */
class MinAndroidVersionGateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientVersionDetector $clientVersionDetector,
        private readonly string $minSupportedAndroidVersion,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 100: after RequestIdSubscriber (200) so the request_id exists for the
        // problem+json response, but before the router/firewall so a too-old client gets
        // 426 rather than 401/404.
        return [
            KernelEvents::REQUEST => ['onRequest', 100],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $floor = trim($this->minSupportedAndroidVersion);
        if ($floor === '') {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return;
        }

        $clientVersion = $this->clientVersionDetector->getClientVersion($request);
        if (version_compare($clientVersion, $floor, '<')) {
            $event->setResponse(new JsonResponse(
                ['detail' => 'A newer version of the app is required.', 'code' => 'upgrade_required'],
                Response::HTTP_UPGRADE_REQUIRED,
            ));
        }
    }
}
