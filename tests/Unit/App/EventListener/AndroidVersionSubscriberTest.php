<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\App\EventListener;

use BikeShare\App\Api\ClientVersionDetector;
use BikeShare\App\Entity\User;
use BikeShare\App\EventListener\AndroidVersionSubscriber;
use BikeShare\Repository\UserClientRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class AndroidVersionSubscriberTest extends TestCase
{
    private const TRACKED_PATH = '/api/v1/rentals';
    private const TRACKED_ROUTE = 'api_v1_rentals';

    public function testRunsAfterResponseIsSent(): void
    {
        $events = AndroidVersionSubscriber::getSubscribedEvents();
        $this->assertSame('onTerminate', $events[KernelEvents::TERMINATE]);
    }

    public function testParsedAndroidVersionIsRecorded(): void
    {
        $repo = $this->createMock(UserClientRepository::class);
        $repo->expects($this->once())
            ->method('recordSeen')
            ->with(42, 'android', '1.2.3');

        $subscriber = $this->createSubscriber('1.2.3', true, $this->buildUser(42), $repo);
        $subscriber->onTerminate($this->createTerminateEvent(self::TRACKED_PATH, self::TRACKED_ROUTE));
    }

    public function testNonApiPathSkipped(): void
    {
        $repo = $this->createMock(UserClientRepository::class);
        $repo->expects($this->never())->method('recordSeen');

        $subscriber = $this->createSubscriber('1.2.3', true, $this->buildUser(1), $repo);
        $subscriber->onTerminate($this->createTerminateEvent('/admin', 'admin_index'));
    }

    #[DataProvider('highFrequencyRouteProvider')]
    public function testHighFrequencyRouteSkipped(string $path, string $routeName): void
    {
        $repo = $this->createMock(UserClientRepository::class);
        $repo->expects($this->never())->method('recordSeen');

        $subscriber = $this->createSubscriber('1.2.3', true, $this->buildUser(1), $repo);
        $subscriber->onTerminate($this->createTerminateEvent($path, $routeName));
    }

    public static function highFrequencyRouteProvider(): array
    {
        return [
            'stand markers polling' => ['/api/v1/stands/markers', 'api_v1_stand_markers'],
            'me/limits per-screen' => ['/api/v1/me/limits', 'api_v1_me_limits'],
            'me/bikes per-screen' => ['/api/v1/me/bikes', 'api_v1_me_bikes'],
            'me/city patch' => ['/api/v1/me/city', 'api_v1_me_city'],
        ];
    }

    public function testUnauthenticatedRequestSkipped(): void
    {
        $repo = $this->createMock(UserClientRepository::class);
        $repo->expects($this->never())->method('recordSeen');

        $subscriber = $this->createSubscriber('1.2.3', true, null, $repo);
        $subscriber->onTerminate($this->createTerminateEvent(self::TRACKED_PATH, self::TRACKED_ROUTE));
    }

    public function testNonAndroidClientSkipped(): void
    {
        $repo = $this->createMock(UserClientRepository::class);
        $repo->expects($this->never())->method('recordSeen');

        $subscriber = $this->createSubscriber('999.0.0', false, $this->buildUser(1), $repo);
        $subscriber->onTerminate($this->createTerminateEvent(self::TRACKED_PATH, self::TRACKED_ROUTE));
    }

    public function testSkippedWhenAndroidDisabled(): void
    {
        $repo = $this->createMock(UserClientRepository::class);
        $repo->expects($this->never())->method('recordSeen');

        $subscriber = $this->createSubscriber(
            '1.2.3',
            true,
            $this->buildUser(1),
            $repo,
            isAndroidAppEnabled: false,
        );
        $subscriber->onTerminate($this->createTerminateEvent(self::TRACKED_PATH, self::TRACKED_ROUTE));
    }

    private function createSubscriber(
        string $clientVersion,
        bool $isParsed,
        ?User $user,
        UserClientRepository $repo,
        bool $isAndroidAppEnabled = true,
    ): AndroidVersionSubscriber {
        $detector = $this->createStub(ClientVersionDetector::class);
        $detector->method('getClientVersion')->willReturn($clientVersion);
        $detector->method('isParsedAndroidVersion')->willReturn($isParsed);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new AndroidVersionSubscriber($detector, $repo, $security, $isAndroidAppEnabled);
    }

    private function createTerminateEvent(string $path, string $routeName): TerminateEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create($path);
        $request->attributes->set('_route', $routeName);

        return new TerminateEvent($kernel, $request, new Response());
    }

    private function buildUser(int $userId): User
    {
        return new User(
            $userId,
            '421951111111',
            'user@example.com',
            'hashedPassword',
            'Bratislava',
            'Test User',
            0,
            true,
            new \DateTimeImmutable('2025-01-01'),
        );
    }
}
