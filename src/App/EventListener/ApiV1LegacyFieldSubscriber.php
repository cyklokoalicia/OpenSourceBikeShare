<?php

declare(strict_types=1);

namespace BikeShare\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiV1LegacyFieldSubscriber implements EventSubscriberInterface
{
    private const RENAMES = [
        'userName' => 'username',
    ];

    public function __construct(private readonly ClientVersionDetector $clientVersionDetector)
    {
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

        if (!$this->clientVersionDetector->requiresLegacyFieldNames($request)) {
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

        $response->setData($this->renameKeys($data, self::RENAMES));
    }

    private function renameKeys(array $data, array $renames): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $newKey = $renames[$key] ?? $key;
            $result[$newKey] = is_array($value) ? $this->renameKeys($value, $renames) : $value;
        }

        return $result;
    }
}
