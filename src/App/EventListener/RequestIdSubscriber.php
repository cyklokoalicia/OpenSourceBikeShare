<?php

declare(strict_types=1);

namespace BikeShare\App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestIdSubscriber implements EventSubscriberInterface
{
    public const ATTRIBUTE_NAME = 'request_id';
    public const HEADER_NAME = 'X-Request-Id';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 200],
            KernelEvents::RESPONSE => ['onResponse', -200],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $requestId = trim((string)$request->headers->get(self::HEADER_NAME, ''));
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(8));
        }

        $request->attributes->set(self::ATTRIBUTE_NAME, $requestId);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $requestId = $event->getRequest()->attributes->get(self::ATTRIBUTE_NAME);
        if (!is_string($requestId) || $requestId === '') {
            throw new \LogicException('Missing request_id attribute in request.');
        }

        $event->getResponse()->headers->set(self::HEADER_NAME, $requestId);
    }
}
