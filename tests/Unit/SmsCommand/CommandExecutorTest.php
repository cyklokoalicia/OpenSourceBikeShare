<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Event\SmsProcessedEvent;
use BikeShare\SmsCommand\AddCommand;
use BikeShare\SmsCommand\CommandDetector;
use BikeShare\SmsCommand\CommandExecutor;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\SmsCommand\SmsCommandInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CommandExecutorTest extends TestCase
{
    private CommandDetector&MockObject $commandDetectorMock;
    private ServiceLocator&MockObject $commandLocatorMock;
    private EventDispatcherInterface&MockObject $eventDispatcherMock;
    private LoggerInterface&MockObject $loggerMock;
    private CommandExecutor $executor;

    protected function setUp(): void
    {
        $this->commandDetectorMock = $this->createMock(CommandDetector::class);
        $this->commandLocatorMock = $this->createMock(ServiceLocator::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->executor = new CommandExecutor(
            $this->commandDetectorMock,
            $this->commandLocatorMock,
            $this->eventDispatcherMock,
            $this->loggerMock,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->commandDetectorMock,
            $this->commandLocatorMock,
            $this->eventDispatcherMock,
            $this->loggerMock,
            $this->executor
        );
    }

    public function testExecuteUnknownCommandWithPossibleCommand(): void
    {
        $user = $this->createStub(User::class);
        $possibleCommandMock = $this->createMock(SmsCommandInterface::class);
        $helpMessage = new TranslatableMessage('command.add.help');

        $this->commandDetectorMock
            ->expects($this->once())
            ->method('detect')
            ->willReturn(['command' => 'UNKNOWN', 'possibleCommand' => 'ADD', 'arguments' => []]);
        $this->commandLocatorMock->expects($this->once())->method('has')->with('ADD')->willReturn(true);
        $this->commandLocatorMock->expects($this->once())->method('get')->with('ADD')->willReturn($possibleCommandMock);
        $possibleCommandMock->expects($this->once())->method('getHelpMessage')->willReturn($helpMessage);
        $this->eventDispatcherMock->expects($this->never())->method('dispatch');
        $this->loggerMock->expects($this->never())->method('error');
        $this->loggerMock->expects($this->never())->method('warning');

        $result = $this->executor->execute('ADD', $user);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.error.more_arguments_needed', $result->getMessage());
        $this->assertSame(['command' => $helpMessage], $result->getParameters());
    }

    public function testExecuteThrowsRuntimeExceptionOnUnknownCommand(): void
    {
        $user = $this->createStub(User::class);

        $this->eventDispatcherMock->expects($this->never())->method('dispatch');
        $this->loggerMock->expects($this->never())->method('error');
        $this->loggerMock->expects($this->never())->method('warning');
        $this->commandDetectorMock
            ->expects($this->once())
            ->method('detect')
            ->willReturn(['command' => 'FOO', 'arguments' => []]);
        $this->commandLocatorMock->expects($this->once())->method('has')->with('FOO')->willReturn(false);
        $this->expectException(\RuntimeException::class);

        $this->executor->execute('FOO', $user);
    }

    public function testExecuteKnownCommand(): void
    {
        $user = $this->createStub(User::class);
        $commandName = 'ADD';
        $arguments = ['email' => 'test@example.com', 'phone' => '123', 'fullName' => 'Test User'];
        $expected = new TranslatableMessage('command.add.success', ['userName' => 'Test User']);

        $this->loggerMock->expects($this->never())->method('error');
        $this->loggerMock->expects($this->never())->method('warning');
        $commandMock = $this->createMock(AddCommand::class);
        $commandMock->expects($this->once())->method('checkPrivileges')->with($user);
        $commandMock
            ->expects($this->once())
            ->method('__invoke')
            ->with($user, ...array_values($arguments))
            ->willReturn($expected);
        $this->commandDetectorMock
            ->expects($this->once())
            ->method('detect')
            ->willReturn(['command' => $commandName, 'arguments' => $arguments]);
        $this->commandLocatorMock->expects($this->once())->method('has')->with($commandName)->willReturn(true);
        $this->commandLocatorMock->expects($this->once())->method('get')->with($commandName)->willReturn($commandMock);
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SmsProcessedEvent::class));

        $this->assertSame($expected, $this->executor->execute('ADD test@example.com 123 Test User', $user));
    }

    public function testExecuteHandlesValidationException(): void
    {
        $user = $this->createStub(User::class);
        $commandName = 'ADD';
        $exception = new ValidationException('user.error.invalid_phone', ['phone' => '123']);

        $this->eventDispatcherMock->expects($this->never())->method('dispatch');
        $this->loggerMock->expects($this->never())->method('error');
        $commandMock = $this->createMock(AddCommand::class);
        $commandMock->expects($this->once())->method('checkPrivileges')->with($user);
        $commandMock
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException($exception);
        $this->commandDetectorMock
            ->expects($this->once())
            ->method('detect')
            ->willReturn([
                'command' => $commandName,
                'arguments' => ['email' => 'test@example.com', 'phone' => '123', 'fullName' => 'Test User'],
            ]);
        $this->commandLocatorMock->expects($this->once())->method('has')->with($commandName)->willReturn(true);
        $this->commandLocatorMock->expects($this->once())->method('get')->with($commandName)->willReturn($commandMock);
        $this->loggerMock->expects($this->once())->method('warning');

        $result = $this->executor->execute('ADD test@example.com 123 Test User', $user);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('user.error.invalid_phone', $result->getMessage());
        $this->assertSame(['phone' => '123'], $result->getParameters());
    }

    public function testExecuteHandlesGenericException(): void
    {
        $user = $this->createStub(User::class);
        $commandName = 'ADD';

        $this->eventDispatcherMock->expects($this->never())->method('dispatch');
        $this->loggerMock->expects($this->never())->method('warning');
        $commandMock = $this->createMock(AddCommand::class);
        $commandMock->expects($this->once())->method('checkPrivileges')->with($user);
        $commandMock->expects($this->once())->method('__invoke')->willThrowException(new \Exception('fail'));
        $this->commandDetectorMock
            ->expects($this->once())
            ->method('detect')
            ->willReturn([
                'command' => $commandName,
                'arguments' => ['email' => 'test@example.com', 'phone' => '123', 'fullName' => 'Test User'],
            ]);
        $this->commandLocatorMock->expects($this->once())->method('has')->with($commandName)->willReturn(true);
        $this->commandLocatorMock->expects($this->once())->method('get')->with($commandName)->willReturn($commandMock);
        $this->loggerMock->expects($this->once())->method('error');

        $result = $this->executor->execute('ADD test@example.com 123 Test User', $user);

        $this->assertInstanceOf(TranslatableMessage::class, $result);
        $this->assertSame('command.error.processing_error', $result->getMessage());
    }
}
