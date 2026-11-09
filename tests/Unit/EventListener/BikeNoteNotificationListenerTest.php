<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\EventListener;

use BikeShare\Event\BikeReturnEvent;
use BikeShare\EventListener\BikeNoteNotificationListener;
use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class BikeNoteNotificationListenerTest extends TestCase
{
    public function testReturnWithoutNoteNotifiesNobody(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->never())->method('findItem');
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->never())->method('notify');

        (new BikeNoteNotificationListener($userRepository, $notifier))(
            new BikeReturnEvent(6, 'STAND1', 4, false)
        );
    }

    public function testReturnWithNoteNotifiesAdmins(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())->method('findItem')->with(4)
            ->willReturn(['userName' => 'Jane', 'number' => '421951111111']);
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())->method('notify')->with(
            $this->equalTo(new Notification(
                NotificationType::BikeNoteAdded,
                new TranslatableMessage('notification.subject.bike_note_added'),
                new TranslatableMessage('bike.note.admin.notification', [
                    'bikeNumber' => 6,
                    'bikeStatus' => new TranslatableMessage('bike.status.at_stand', ['standName' => 'STAND1']),
                    'userName' => 'Jane',
                    'phone' => '421951111111',
                    'userNote' => 'Rear brake squeaks',
                ])
            )),
            $this->equalTo(new AdminRecipients())
        );

        (new BikeNoteNotificationListener($userRepository, $notifier))(
            new BikeReturnEvent(6, 'STAND1', 4, false, 'Rear brake squeaks')
        );
    }
}
