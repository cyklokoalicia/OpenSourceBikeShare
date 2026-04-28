<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsCommand\AddCommand;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\User\UserRegistration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class AddCommandTest extends TestCase
{
    private UserRegistration&MockObject $userRegistrationMock;
    private UserRepository&MockObject $userRepositoryMock;
    private PhonePurifierInterface&MockObject $phonePurifierMock;
    private AddCommand $command;

    protected function setUp(): void
    {
        $this->userRegistrationMock = $this->createMock(UserRegistration::class);
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->phonePurifierMock = $this->createMock(PhonePurifierInterface::class);
        $this->command = new AddCommand(
            $this->userRegistrationMock,
            $this->userRepositoryMock,
            $this->phonePurifierMock
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->userRegistrationMock,
            $this->userRepositoryMock,
            $this->phonePurifierMock,
            $this->command
        );
    }

    public function testInvokeThrowsWhenInvalidPhone(): void
    {
        $this->phonePurifierMock->expects($this->once())->method('isValid')->with('123')->willReturn(false);
        $this->phonePurifierMock->expects($this->never())->method('purify');
        $this->userRepositoryMock->expects($this->never())->method('findItemByPhoneNumber');
        $this->userRepositoryMock->expects($this->never())->method('findItemByEmail');
        $this->userRegistrationMock->expects($this->never())->method('register');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'a@b.com', '123', 'Test User');
    }

    public function testInvokeThrowsWhenInvalidEmail(): void
    {
        $this->phonePurifierMock->expects($this->once())->method('isValid')->with('123')->willReturn(true);
        $this->phonePurifierMock->expects($this->once())->method('purify')->with('123')->willReturn('421123');
        $this->userRepositoryMock->expects($this->never())->method('findItemByPhoneNumber');
        $this->userRepositoryMock->expects($this->never())->method('findItemByEmail');
        $this->userRegistrationMock->expects($this->never())->method('register');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'invalid', '123', 'Test User');
    }

    public function testInvokeThrowsWhenPhoneRegistered(): void
    {
        $this->phonePurifierMock->expects($this->once())->method('isValid')->with('123')->willReturn(true);
        $this->phonePurifierMock->expects($this->once())->method('purify')->with('123')->willReturn('421123');
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItemByPhoneNumber')
            ->with('421123')
            ->willReturn(['id' => 1]);
        $this->userRegistrationMock->expects($this->never())->method('register');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'a@b.com', '123', 'Test User');
    }

    public function testInvokeThrowsWhenEmailRegistered(): void
    {
        $this->phonePurifierMock->expects($this->once())->method('isValid')->with('123')->willReturn(true);
        $this->phonePurifierMock->expects($this->once())->method('purify')->with('123')->willReturn('421123');
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItemByPhoneNumber')
            ->with('421123')
            ->willReturn(null);
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItemByEmail')
            ->with('a@b.com')
            ->willReturn(['id' => 1]);
        $this->userRegistrationMock->expects($this->never())->method('register');
        $this->expectException(ValidationException::class);

        ($this->command)($this->createStub(User::class), 'a@b.com', '123', 'Test User');
    }

    public function testInvokeSuccessRegister(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->expects($this->once())->method('getCity')->willReturn('Bratislava');
        $this->phonePurifierMock->expects($this->once())->method('isValid')->with('123')->willReturn(true);
        $this->phonePurifierMock->expects($this->once())->method('purify')->with('123')->willReturn('421123456789');
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItemByPhoneNumber')
            ->with('421123456789')
            ->willReturn(null);
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItemByEmail')
            ->with('test@example.com')
            ->willReturn(null);
        $this->userRegistrationMock
            ->expects($this->once())
            ->method('register')
            ->with('421123456789', 'test@example.com', $this->isString(), 'Bratislava', 'Test User', 0);

        $result = ($this->command)($userMock, 'test@example.com', '123', 'Test User');

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.add.success', $result->getMessage());
        $this->assertSame(['userName' => 'Test User'], $result->getParameters());
    }

    public function testGetHelpMessage(): void
    {
        $this->phonePurifierMock->expects($this->never())->method('isValid');
        $this->userRepositoryMock->expects($this->never())->method('findItemByPhoneNumber');
        $this->userRepositoryMock->expects($this->never())->method('findItemByEmail');
        $this->userRegistrationMock->expects($this->never())->method('register');

        $help = $this->command->getHelpMessage();
        $this->assertInstanceOf(TranslatableMessage::class, $help);
        $this->assertSame('command.add.help', $help->getMessage());
    }
}
