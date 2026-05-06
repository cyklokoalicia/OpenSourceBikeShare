<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\EventListener;

use BikeShare\Event\UserVerificationCompletedEvent;
use BikeShare\EventListener\NewUserAdminNotifierEventListener;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class NewUserAdminNotifierEventListenerTest extends TestCase
{
    private AdminNotifier&MockObject $adminNotifierMock;
    private UserRepository&MockObject $userRepositoryMock;
    private RegistrationRepository&MockObject $registrationRepositoryMock;

    protected function setUp(): void
    {
        $this->adminNotifierMock = $this->createMock(AdminNotifier::class);
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->registrationRepositoryMock = $this->createMock(RegistrationRepository::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->adminNotifierMock,
            $this->userRepositoryMock,
            $this->registrationRepositoryMock,
        );
    }

    private function createListener(bool $isSmsSystemEnabled): NewUserAdminNotifierEventListener
    {
        return new NewUserAdminNotifierEventListener(
            $isSmsSystemEnabled,
            $this->adminNotifierMock,
            $this->userRepositoryMock,
            $this->registrationRepositoryMock,
        );
    }

    public function testNotifiesAdminsWhenFullyVerified(): void
    {
        $userId = 42;

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($userId)
            ->willReturn([
                'userId' => $userId,
                'userName' => 'John Doe',
                'mail' => 'john@example.com',
                'number' => '421900111222',
                'isNumberConfirmed' => 1,
            ]);
        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findItemByUserId')
            ->with($userId)
            ->willReturn(null);

        $this->adminNotifierMock
            ->expects($this->once())
            ->method('notify')
            ->with(
                $this->callback(function (TranslatableMessage $msg): bool {
                    $this->assertSame('admin.notification.new_verified_user', $msg->getMessage());
                    $this->assertSame(
                        [
                            'userName' => 'John Doe',
                            'email' => 'john@example.com',
                            'phone' => '421900111222',
                        ],
                        $msg->getParameters()
                    );
                    return true;
                }),
                false
            );

        ($this->createListener(isSmsSystemEnabled: true))(new UserVerificationCompletedEvent($userId));
    }

    public function testDoesNothingWhenPhoneNotConfirmedAndSmsEnabled(): void
    {
        $userId = 42;

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($userId)
            ->willReturn([
                'userId' => $userId,
                'userName' => 'John Doe',
                'mail' => 'john@example.com',
                'number' => '421900111222',
                'isNumberConfirmed' => 0,
            ]);
        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findItemByUserId')
            ->with($userId)
            ->willReturn(null);
        $this->adminNotifierMock->expects($this->never())->method('notify');

        ($this->createListener(isSmsSystemEnabled: true))(new UserVerificationCompletedEvent($userId));
    }

    public function testNotifiesWhenSmsDisabledEvenIfPhoneNotConfirmed(): void
    {
        $userId = 42;

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($userId)
            ->willReturn([
                'userId' => $userId,
                'userName' => 'John Doe',
                'mail' => 'john@example.com',
                'number' => '421900111222',
                'isNumberConfirmed' => 0,
            ]);
        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findItemByUserId')
            ->with($userId)
            ->willReturn(null);

        $this->adminNotifierMock
            ->expects($this->once())
            ->method('notify')
            ->with($this->isInstanceOf(TranslatableMessage::class), false);

        ($this->createListener(isSmsSystemEnabled: false))(new UserVerificationCompletedEvent($userId));
    }

    public function testDoesNothingWhenUserMissing(): void
    {
        $userId = 42;

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($userId)
            ->willReturn(null);
        $this->registrationRepositoryMock->expects($this->never())->method('findItemByUserId');
        $this->adminNotifierMock->expects($this->never())->method('notify');

        ($this->createListener(isSmsSystemEnabled: true))(new UserVerificationCompletedEvent($userId));
    }

    public function testDoesNothingWhenEmailRegistrationStillPending(): void
    {
        $userId = 42;

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($userId)
            ->willReturn([
                'userId' => $userId,
                'userName' => 'John Doe',
                'mail' => 'john@example.com',
                'number' => '421900111222',
                'isNumberConfirmed' => 1,
            ]);
        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findItemByUserId')
            ->with($userId)
            ->willReturn(['userId' => $userId, 'userKey' => 'pending']);
        $this->adminNotifierMock->expects($this->never())->method('notify');

        ($this->createListener(isSmsSystemEnabled: true))(new UserVerificationCompletedEvent($userId));
    }

    public function testDoesNothingWhenSmsDisabledAndEmailRegistrationStillPending(): void
    {
        $userId = 42;

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItem')
            ->with($userId)
            ->willReturn([
                'userId' => $userId,
                'userName' => 'John Doe',
                'mail' => 'john@example.com',
                'number' => '421900111222',
                'isNumberConfirmed' => 0,
            ]);
        $this->registrationRepositoryMock
            ->expects($this->once())
            ->method('findItemByUserId')
            ->with($userId)
            ->willReturn(['userId' => $userId, 'userKey' => 'pending']);
        $this->adminNotifierMock->expects($this->never())->method('notify');

        ($this->createListener(isSmsSystemEnabled: false))(new UserVerificationCompletedEvent($userId));
    }
}
