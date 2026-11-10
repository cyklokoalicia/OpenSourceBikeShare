<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\EventListener;

use BikeShare\Event\BikeRevertEvent;
use BikeShare\EventListener\BikeRevertEventListener;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\UserRecipient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class BikeRevertEventListenerTest extends TestCase
{
    private NotifierInterface&MockObject $notifierMock;
    private BikeRevertEventListener $listener;

    protected function setUp(): void
    {
        $this->notifierMock = $this->createMock(NotifierInterface::class);
        $this->listener = new BikeRevertEventListener($this->notifierMock);
    }

    protected function tearDown(): void
    {
        unset($this->notifierMock, $this->listener);
    }

    public function testNotifiesPreviousOwner(): void
    {
        $this->notifierMock
            ->expects($this->once())
            ->method('notify')
            ->with(
                $this->equalTo(
                    new Notification(
                        NotificationType::BikeRevertedOwner,
                        new TranslatableMessage('notification.subject.bike_reverted_owner'),
                        new TranslatableMessage('bike.revert.notification.previous_owner', ['bikeNumber' => 5])
                    )
                ),
                $this->equalTo(new UserRecipient(7))
            );

        ($this->listener)(new BikeRevertEvent(5, 99, 7));
    }

    public function testNoSmsWhenOwnerRevertsOwnBike(): void
    {
        $this->notifierMock->expects($this->never())->method('notify');

        ($this->listener)(new BikeRevertEvent(5, 7, 7));
    }
}
