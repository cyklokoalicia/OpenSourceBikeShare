<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Event\SmsProcessedEvent;
use BikeShare\SmsCommand\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

class CommandExecutor
{
    public function __construct(
        private readonly CommandDetector $commandDetector,
        private readonly ServiceLocator $commandLocator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(string $message, User $user): TranslatableInterface
    {
        $commandInfo = $this->commandDetector->detect($message);
        if (
            $commandInfo['command'] === 'UNKNOWN'
            && !empty($commandInfo['possibleCommand'])
            && $this->commandLocator->has($commandInfo['possibleCommand'])
        ) {
            /* @var \Closure|SmsCommandInterface $possibleCommand */
            $possibleCommand = $this->commandLocator->get($commandInfo['possibleCommand']);

            return new TranslatableMessage(
                'command.error.more_arguments_needed',
                ['command' => $possibleCommand->getHelpMessage()]
            );
        } elseif (!$this->commandLocator->has($commandInfo['command'])) {
            throw new \RuntimeException('Unknown command');
        }

        $commandName = $commandInfo['command'];
        try {
            /* @var \Closure|SmsCommandInterface $command */
            $command = $this->commandLocator->get($commandName);

            $command->checkPrivileges($user);

            $arguments = [];
            $params = (new \ReflectionMethod($command, '__invoke'))->getParameters();
            foreach ($params as $param) {
                if ($param->getType()->getName() === User::class) {
                    $arguments[] = $user;
                    continue;
                }

                $arguments[] = $commandInfo['arguments'][$param->getName()] ?? null;
            }

            $message = $command(...$arguments);

            $this->eventDispatcher->dispatch(
                new SmsProcessedEvent($user, $commandName, $commandInfo['arguments'], $message)
            );
        } catch (ValidationException $e) {
            $this->logger->warning(
                'Validation error',
                ['user' => $user, 'command' => $commandName, 'exception' => $e]
            );
            $message = new TranslatableMessage($e->getMessage(), $e->getParameters());
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error executing command',
                ['user' => $user, 'command' => $commandName, 'exception' => $e]
            );
            $message = new TranslatableMessage('command.error.processing_error');
        }

        return $message;
    }
}
