<?php

declare(strict_types=1);

namespace BikeShare\App\EventListener;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiV1ResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', 100],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/v1')) {
            return;
        }

        $response = $event->getResponse();
        if (!$response instanceof JsonResponse) {
            return;
        }

        $requestId = $this->getRequestId($request);

        $contentType = (string)$response->headers->get('Content-Type');
        if (str_contains($contentType, 'application/problem+json')) {
            $event->setResponse($this->toProblemResponse($response, $path, $requestId));
            return;
        }

        $status = $response->getStatusCode();

        if ($status >= Response::HTTP_BAD_REQUEST) {
            $event->setResponse($this->toProblemResponse($response, $path, $requestId));
            return;
        }

        $timestamp = $this->clock->now()->format(\DateTimeInterface::ATOM);
        $decoded = $this->decodeResponse($response);

        if (is_array($decoded) && array_key_exists('data', $decoded) && array_key_exists('meta', $decoded)) {
            $meta = is_array($decoded['meta']) ? $decoded['meta'] : [];
            $decoded['meta'] = array_merge(
                [
                    'requestId' => $requestId,
                    'timestamp' => $timestamp,
                ],
                $meta
            );
            $response->setData($decoded);
            return;
        }

        $response->setData(
            [
                'data' => $decoded,
                'meta' => [
                    'requestId' => $requestId,
                    'timestamp' => $timestamp,
                ],
            ]
        );
    }

    private function toProblemResponse(
        JsonResponse $sourceResponse,
        string $path,
        string $requestId,
    ): JsonResponse {
        $status = $sourceResponse->getStatusCode();
        $title = Response::$statusTexts[$status] ?? 'Error';
        $decoded = $this->decodeResponse($sourceResponse);

        $problem = is_array($decoded) ? $decoded : [];
        if (!isset($problem['type']) || !is_scalar($problem['type']) || trim((string)$problem['type']) === '') {
            $problem['type'] = 'about:blank';
        }

        if (!isset($problem['title']) || !is_scalar($problem['title']) || trim((string)$problem['title']) === '') {
            $problem['title'] = $title;
        }

        $problem['status'] = $status;
        $problem['detail'] = $this->resolveDetail($status, $problem);

        if (
            !isset($problem['instance'])
            || !is_scalar($problem['instance'])
            || trim((string)$problem['instance']) === ''
        ) {
            $problem['instance'] = $path;
        }

        if (
            !isset($problem['requestId'])
            || !is_scalar($problem['requestId'])
            || trim((string)$problem['requestId']) === ''
        ) {
            $problem['requestId'] = $requestId;
        }

        $normalized = new JsonResponse(
            $problem,
            $status,
            $sourceResponse->headers->allPreserveCaseWithoutCookies()
        );
        foreach ($sourceResponse->headers->getCookies() as $cookie) {
            $normalized->headers->setCookie($cookie);
        }
        $normalized->headers->set('Content-Type', 'application/problem+json');

        return $normalized;
    }

    private function decodeResponse(JsonResponse $response): mixed
    {
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return null;
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private function getRequestId(Request $request): string
    {
        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE_NAME);
        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        throw new \LogicException('Missing request_id attribute in request.');
    }

    private function resolveDetail(int $status, mixed $decoded): string
    {
        if (is_array($decoded) && array_key_exists('detail', $decoded) && is_scalar($decoded['detail'])) {
            $detail = trim((string)$decoded['detail']);
            if ($detail !== '') {
                return $detail;
            }
        }

        return Response::$statusTexts[$status] ?? 'Error';
    }
}
