<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use BikeShare\App\EventListener\MinAndroidVersionGateSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class MinAndroidVersionGateSubscriberTest extends TestCase
{
    private const FLOOR = '1.2.0';
    private const API_PATH = '/api/v1/auth/cities';

    public function testSubscribedEventPriorityRunsAfterRequestIdBeforeFirewall(): void
    {
        $events = MinAndroidVersionGateSubscriber::getSubscribedEvents();
        $this->assertSame(['onRequest', 100], $events[KernelEvents::REQUEST]);
    }

    public function testNoGateWhenFloorEmpty(): void
    {
        $event = $this->dispatch('', self::API_PATH, 'OpenSourceBikeShare-Android/1.0.0 (1)');
        $this->assertNull($event->getResponse());
    }

    public function testBlocksAndroidBelowFloor(): void
    {
        $event = $this->dispatch(self::FLOOR, self::API_PATH, 'OpenSourceBikeShare-Android/1.0.0 (1)');

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(426, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true);
        $this->assertSame('upgrade_required', $payload['code']);
    }

    public function testAllowsAndroidAtFloor(): void
    {
        $event = $this->dispatch(self::FLOOR, self::API_PATH, 'OpenSourceBikeShare-Android/1.2.0 (5)');
        $this->assertNull($event->getResponse());
    }

    public function testAllowsAndroidAboveFloor(): void
    {
        $event = $this->dispatch(self::FLOOR, self::API_PATH, 'OpenSourceBikeShare-Android/2.0.0 (9)');
        $this->assertNull($event->getResponse());
    }

    public function testNeverBlocksBrowser(): void
    {
        // Browsers/admin/curl map to 999.0.0 — never below a real floor.
        $event = $this->dispatch(self::FLOOR, self::API_PATH, 'Mozilla/5.0 (X11; Linux x86_64)');
        $this->assertNull($event->getResponse());
    }

    public function testBlocksLegacyOkhttpClient(): void
    {
        // Old Android with no custom UA maps to 0.0.0 — below any floor, so blocked.
        $event = $this->dispatch(self::FLOOR, self::API_PATH, 'okhttp/4.12.0');

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(426, $response->getStatusCode());
    }

    public function testSkipsNonApiPath(): void
    {
        $event = $this->dispatch(self::FLOOR, '/login', 'OpenSourceBikeShare-Android/1.0.0 (1)');
        $this->assertNull($event->getResponse());
    }

    public function testSkipsSubRequest(): void
    {
        $event = $this->dispatch(
            self::FLOOR,
            self::API_PATH,
            'OpenSourceBikeShare-Android/1.0.0 (1)',
            HttpKernelInterface::SUB_REQUEST,
        );
        $this->assertNull($event->getResponse());
    }

    private function dispatch(
        string $floor,
        string $path,
        string $userAgent,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        $subscriber = new MinAndroidVersionGateSubscriber(new ClientVersionDetector(), $floor);

        $request = Request::create($path);
        $request->headers->set('User-Agent', $userAgent);

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);
        $subscriber->onRequest($event);

        return $event;
    }
}
