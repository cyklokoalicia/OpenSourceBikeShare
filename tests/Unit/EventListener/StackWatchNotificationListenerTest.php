<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\EventListener;

use BikeShare\Event\StackWatchViolationEvent;
use BikeShare\EventListener\StackWatchNotificationListener;
use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Repository\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class StackWatchNotificationListenerTest extends TestCase
{
    private NotifierInterface&MockObject $notifierMock;
    private UserRepository&MockObject $userRepositoryMock;

    protected function setUp(): void
    {
        $this->notifierMock = $this->createMock(NotifierInterface::class);
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
    }

    protected function tearDown(): void
    {
        unset($this->notifierMock, $this->userRepositoryMock);
    }

    public function testUnknownStackTopBikeStaysNull(): void
    {
        $this->userRepositoryMock->method('findItem')->with(7)->willReturn(['userName' => 'Jane']);

        $this->notifierMock
            ->expects($this->once())
            ->method('notify')
            ->with(
                $this->callback(function (Notification $notification): bool {
                    $message = $notification->body;
                    $this->assertSame(NotificationType::StackWatch, $notification->type);
                    $this->assertInstanceOf(TranslatableMessage::class, $message);
                    $this->assertSame('bike.rent.admin.stack_watch', $message->getMessage());
                    $params = $message->getParameters();
                    $this->assertArrayHasKey('stackTopBike', $params);
                    $this->assertNull($params['stackTopBike']);
                    $this->assertSame(5, $params['bikeNumber']);
                    $this->assertSame('Jane', $params['userName']);
                    $this->assertSame('STAND1', $params['standName']);

                    return true;
                }),
                $this->equalTo(new AdminRecipients())
            );

        (new StackWatchNotificationListener($this->userRepositoryMock, $this->notifierMock))(
            new StackWatchViolationEvent(5, 7, null, 'STAND1')
        );
    }
}
