<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\EventListener;

use BikeShare\Event\BikeRevertEvent;
use BikeShare\EventListener\BikeRevertEventListener;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\SmsSenderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class BikeRevertEventListenerTest extends TestCase
{
    private UserRepository&MockObject $userRepositoryMock;
    private SmsSenderInterface&MockObject $smsSenderMock;
    private UserSettingsRepository&MockObject $userSettingsRepositoryMock;
    private BikeRevertEventListener $listener;

    protected function setUp(): void
    {
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->smsSenderMock = $this->createMock(SmsSenderInterface::class);
        $this->userSettingsRepositoryMock = $this->createMock(UserSettingsRepository::class);

        $this->listener = new BikeRevertEventListener(
            $this->userRepositoryMock,
            $this->smsSenderMock,
            $this->userSettingsRepositoryMock,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->userRepositoryMock,
            $this->smsSenderMock,
            $this->userSettingsRepositoryMock,
            $this->listener,
        );
    }

    public function testNotifiesPreviousOwnerInTheirLocale(): void
    {
        $bikeNumber = 42;
        $previousOwnerId = 7;

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($previousOwnerId)
            ->willReturn(['number' => '421951111111']);
        $this->userSettingsRepositoryMock
            ->expects($this->once())
            ->method('findByUserId')
            ->with($previousOwnerId)
            ->willReturn(['locale' => 'de']);
        $this->smsSenderMock
            ->expects($this->once())
            ->method('send')
            ->with(
                '421951111111',
                $this->callback(function (TranslatableMessage $msg) use ($bikeNumber): bool {
                    $this->assertSame('bike.revert.notification.previous_owner', $msg->getMessage());
                    $this->assertSame(['bikeNumber' => $bikeNumber], $msg->getParameters());
                    return true;
                }),
                'de'
            );

        ($this->listener)(new BikeRevertEvent($bikeNumber, 99, $previousOwnerId));
    }

    public function testFallsBackToNullLocaleWhenSettingsMissing(): void
    {
        $this->userRepositoryMock->expects($this->once())->method('findItem')->willReturn(['number' => '111']);
        $this->userSettingsRepositoryMock->expects($this->once())->method('findByUserId')->willReturn([]);
        $this->smsSenderMock
            ->expects($this->once())
            ->method('send')
            ->with('111', $this->isInstanceOf(TranslatableMessage::class), null);

        ($this->listener)(new BikeRevertEvent(1, 2, 3));
    }

    public function testSkipsNotificationWhenPhoneMissing(): void
    {
        $this->userRepositoryMock->expects($this->once())->method('findItem')->willReturn(['number' => null]);
        $this->userSettingsRepositoryMock->expects($this->never())->method('findByUserId');
        $this->smsSenderMock->expects($this->never())->method('send');

        ($this->listener)(new BikeRevertEvent(1, 2, 3));
    }

    public function testSkipsNotificationWhenPreviousOwnerIsRevertedByUser(): void
    {
        $this->userRepositoryMock->expects($this->once())->method('findItem')->willReturn(['number' => '111']);
        $this->userSettingsRepositoryMock->expects($this->never())->method('findByUserId');
        $this->smsSenderMock->expects($this->never())->method('send');

        ($this->listener)(new BikeRevertEvent(1, 5, 5));
    }
}
