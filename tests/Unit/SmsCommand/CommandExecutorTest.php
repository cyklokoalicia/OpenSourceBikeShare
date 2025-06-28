<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Event\SmsProcessedEvent;
use BikeShare\SmsCommand\AddCommand;
use BikeShare\SmsCommand\CommandDetector;
use BikeShare\SmsCommand\CommandExecutor;
use BikeShare\SmsCommand\SmsCommandInterface;
use BikeShare\SmsCommand\Exception\ValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommandExecutorTest extends TestCase
{
    /** @var CommandDetector|MockObject */
    private $commandDetectorMock;
    /** @var ServiceLocator|MockObject */
    private $commandLocatorMock;
    /** @var EventDispatcherInterface|MockObject */
    private $eventDispatcherMock;
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var LoggerInterface|MockObject */
    private $loggerMock;

    private CommandExecutor $executor;

    protected function setUp(): void
    {
        $this->commandDetectorMock = $this->createMock(CommandDetector::class);
        $this->commandLocatorMock = $this->createMock(ServiceLocator::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->executor = new CommandExecutor(
            $this->commandDetectorMock,
            $this->commandLocatorMock,
            $this->eventDispatcherMock,
            $this->translatorMock,
            $this->loggerMock,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->commandDetectorMock,
            $this->commandLocatorMock,
            $this->eventDispatcherMock,
            $this->translatorMock,
            $this->loggerMock,
            $this->executor
        );
    }

    public function testExecuteUnknownCommand(): void
    {
        $user = $this->createMock(User::class);
        $possibleCommandMock = $this->createMock(SmsCommandInterface::class);

        $this->commandDetectorMock
            ->expects($this->once())
            ->method('detect')
            ->willReturn(['command' => 'UNKNOWN', 'possibleCommand' => 'ADD', 'arguments' => []]);
        $this->commandLocatorMock->expects($this->once())->method('has')->with('ADD')->willReturn(true);
        $this->commandLocatorMock->expects($this->once())->method('get')->with('ADD')->willReturn($possibleCommandMock);
        $possibleCommandMock->expects($this->once())->method('getHelpMessage')->willReturn('help message');
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('Error. More arguments needed, use command {command}', ['command' => 'help message'])
            ->willReturn('translated error');

        $this->assertEquals('translated error', $this->executor->execute('ADD', $user));
    }

    public function testExecuteThrowsRuntimeExceptionOnUnknownCommand(): void
    {
        $user = $this->createMock(User::class);

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
        $user = $this->createMock(User::class);
        $commandName = 'ADD';
        $arguments = ['email' => 'test@example.com', 'phone' => '123', 'fullName' => 'Test User'];

        $commandMock = $this->createMock(AddCommand::class);
        $commandMock->expects($this->once())->method('checkPrivileges')->with($user);
        $commandMock
            ->expects($this->once())
            ->method('__invoke')
            ->with($user, ...array_values($arguments))
            ->willReturn('ok');
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

        $this->assertEquals('ok', $this->executor->execute('ADD test@example.com 123 Test User', $user));
    }

    public function testExecuteHandlesValidationException(): void
    {
        $user = $this->createMock(User::class);
        $commandName = 'ADD';

        $commandMock = $this->createMock(AddCommand::class);
        $commandMock->expects($this->once())->method('checkPrivileges')->with($user);
        $commandMock
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new ValidationException('validation error'));
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

        $this->assertEquals('validation error', $this->executor->execute('ADD test@example.com 123 Test User', $user));
    }

    public function testExecuteHandlesGenericException(): void
    {
        $user = $this->createMock(User::class);
        $commandName = 'ADD';

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
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with('An error occurred while processing your request.')
            ->willReturn('generic error');

        $this->assertEquals('generic error', $this->executor->execute('ADD test@example.com 123 Test User', $user));
    }
}
