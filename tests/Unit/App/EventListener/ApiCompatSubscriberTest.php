<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use BikeShare\App\Api\Compat\ApiCompatTransformRegistry;
use BikeShare\App\Api\Compat\ApiResponseTransformInterface;
use BikeShare\App\EventListener\ApiCompatSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiCompatSubscriberTest extends TestCase
{
    public function testSubscribedEventsHasCorrectPriority(): void
    {
        $events = ApiCompatSubscriber::getSubscribedEvents();
        $this->assertSame(['onResponse', 110], $events[KernelEvents::RESPONSE]);
    }

    public function testTransformAppliedForOldClient(): void
    {
        $transform = $this->createStub(ApiResponseTransformInterface::class);
        $transform->method('transform')->willReturnCallback(
            fn(array $data) => array_map(
                fn($item) => is_array($item)
                    ? array_combine(
                        array_map(fn($k) => $k === 'userName' ? 'username' : $k, array_keys($item)),
                        array_values($item)
                    )
                    : $item,
                $data
            )
        );

        $subscriber = $this->createSubscriber('1.0.0', [$transform]);
        $response = new JsonResponse([
            ['userId' => 1, 'userName' => 'John'],
        ]);

        $event = $this->createEvent('/api/v1/admin/users', 'api_v1_admin_users', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('John', $data[0]['username']);
        $this->assertArrayNotHasKey('userName', $data[0]);
    }

    public function testNoTransformForNewClient(): void
    {
        $subscriber = $this->createSubscriber('999.0.0', []);
        $response = new JsonResponse([
            ['userId' => 1, 'userName' => 'John'],
        ]);

        $event = $this->createEvent('/api/v1/admin/users', 'api_v1_admin_users', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('John', $data[0]['userName']);
    }

    public function testErrorResponseSkipped(): void
    {
        $subscriber = $this->createSubscriber('0.0.0', [$this->createStub(ApiResponseTransformInterface::class)]);
        $response = new JsonResponse(['detail' => 'Not found'], Response::HTTP_NOT_FOUND);

        $event = $this->createEvent('/api/v1/admin/users/999', 'api_v1_admin_user_item', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Not found', $data['detail']);
    }

    public function testNonApiPathSkipped(): void
    {
        $subscriber = $this->createSubscriber('0.0.0', [$this->createStub(ApiResponseTransformInterface::class)]);
        $response = new JsonResponse(['userName' => 'John']);

        $event = $this->createEvent('/login', '', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('John', $data['userName']);
    }

    public function testMultipleTransformsAppliedInOrder(): void
    {
        $transform1 = $this->createStub(ApiResponseTransformInterface::class);
        $transform1->method('transform')->willReturnCallback(
            fn(array $data) => array_merge($data, ['step1' => true])
        );

        $transform2 = $this->createStub(ApiResponseTransformInterface::class);
        $transform2->method('transform')->willReturnCallback(
            fn(array $data) => array_merge($data, ['step2' => true])
        );

        $subscriber = $this->createSubscriber('0.0.0', [$transform1, $transform2]);
        $response = new JsonResponse(['original' => true]);

        $event = $this->createEvent('/api/v1/admin/users', 'api_v1_admin_users', $response);
        $subscriber->onResponse($event);

        $data = json_decode($event->getResponse()->getContent(), true);
        $this->assertTrue($data['step1']);
        $this->assertTrue($data['step2']);
        $this->assertTrue($data['original']);
    }

    /**
     * @param ApiResponseTransformInterface[] $transforms
     */
    private function createSubscriber(string $clientVersion, array $transforms): ApiCompatSubscriber
    {
        $detector = $this->createStub(ClientVersionDetector::class);
        $detector->method('getClientVersion')->willReturn($clientVersion);

        $registry = $this->createStub(ApiCompatTransformRegistry::class);
        $registry->method('getTransformsFor')->willReturn($transforms);

        return new ApiCompatSubscriber($detector, $registry);
    }

    private function createEvent(string $path, string $routeName, JsonResponse $response): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($path);
        $request->attributes->set('_route', $routeName);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
