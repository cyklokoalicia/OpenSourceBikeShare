<?php

declare(strict_types=1);

namespace BikeShare\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use BikeShare\App\Api\Compat\ApiCompatTransformRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiCompatSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClientVersionDetector $clientVersionDetector,
        private readonly ApiCompatTransformRegistry $registry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', 110],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/v1')) {
            return;
        }

        $response = $event->getResponse();
        if (!$response instanceof JsonResponse) {
            return;
        }

        if ($response->getStatusCode() >= Response::HTTP_BAD_REQUEST) {
            return;
        }

        $clientVersion = $this->clientVersionDetector->getClientVersion($request);
        $routeName = $request->attributes->get('_route', '');
        $transforms = $this->registry->getTransformsFor($clientVersion, $routeName);

        if (empty($transforms)) {
            return;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (!is_array($data)) {
            return;
        }

        foreach ($transforms as $transform) {
            $data = $transform->transform($data);
        }

        $response->setData($data);
    }
}
