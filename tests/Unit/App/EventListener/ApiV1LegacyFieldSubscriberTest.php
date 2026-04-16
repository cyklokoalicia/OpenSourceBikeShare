<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use BikeShare\App\EventListener\ApiV1LegacyFieldSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiV1LegacyFieldSubscriberTest extends TestCase
{
    public function testSubscribedEventsHasCorrectPriority(): void
    {
        $events = ApiV1LegacyFieldSubscriber::getSubscribedEvents();
        $this->assertSame(['onResponse', 110], $events[KernelEvents::RESPONSE]);
    }

    public function testLegacyClientGetsRenamedFields(): void
    {
        $subscriber = $this->createSubscriber(requiresLegacy: true);
        $response = new JsonResponse([
            ['userId' => 1, 'userName' => 'John', 'mail' => 'john@test.com'],
            ['userId' => 2, 'userName' => 'Jane', 'mail' => 'jane@test.com'],
        ]);

        $event = $this->createEvent('/api/v1/admin/users', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('John', $data[0]['username']);
        $this->assertArrayNotHasKey('userName', $data[0]);
        $this->assertSame('Jane', $data[1]['username']);
    }

    public function testLegacyClientGetsRenamedNestedFields(): void
    {
        $subscriber = $this->createSubscriber(requiresLegacy: true);
        $response = new JsonResponse([
            'notes' => 'some notes',
            'history' => [
                ['action' => 'RENT', 'userName' => 'John'],
                ['action' => 'RETURN', 'userName' => 'Jane'],
            ],
        ]);

        $event = $this->createEvent('/api/v1/admin/bikes/1/last-usage', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('John', $data['history'][0]['username']);
        $this->assertArrayNotHasKey('userName', $data['history'][0]);
    }

    public function testModernClientGetsUnchangedResponse(): void
    {
        $subscriber = $this->createSubscriber(requiresLegacy: false);
        $response = new JsonResponse([
            ['userId' => 1, 'userName' => 'John'],
        ]);

        $event = $this->createEvent('/api/v1/admin/users', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('John', $data[0]['userName']);
        $this->assertArrayNotHasKey('username', $data[0]);
    }

    public function testErrorResponseNotTransformed(): void
    {
        $subscriber = $this->createSubscriber(requiresLegacy: true);
        $response = new JsonResponse(['detail' => 'Not found'], Response::HTTP_NOT_FOUND);

        $event = $this->createEvent('/api/v1/admin/users/999', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Not found', $data['detail']);
    }

    public function testNonApiPathSkipped(): void
    {
        $subscriber = $this->createSubscriber(requiresLegacy: true);
        $response = new JsonResponse(['userName' => 'John']);

        $event = $this->createEvent('/login', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('John', $data['userName']);
    }

    private function createSubscriber(bool $requiresLegacy): ApiV1LegacyFieldSubscriber
    {
        $detector = $this->createStub(ClientVersionDetector::class);
        $detector->method('requiresLegacyFieldNames')->willReturn($requiresLegacy);

        return new ApiV1LegacyFieldSubscriber($detector);
    }

    private function createEvent(string $path, JsonResponse $response): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($path);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
