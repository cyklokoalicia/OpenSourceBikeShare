<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Event\SmsProcessedEvent;
use BikeShare\SmsCommand\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CommandExecutor
{
    private CommandDetector $commandDetector;
    private ServiceLocator $commandLocator;
    private EventDispatcherInterface $eventDispatcher;
    private TranslatorInterface $translator;
    private LoggerInterface $logger;

    public function __construct(
        CommandDetector $commandDetector,
        ServiceLocator $commandLocator,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator,
        LoggerInterface $logger
    ) {
        $this->commandDetector = $commandDetector;
        $this->commandLocator = $commandLocator;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    public function execute(string $message, User $user): string
    {
        $commandInfo = $this->commandDetector->detect($message);
        if (
            $commandInfo['command'] === 'UNKNOWN'
            && !empty($commandInfo['possibleCommand'])
            && $this->commandLocator->has($commandInfo['possibleCommand'])
        ) {
            /* @var \Closure|SmsCommandInterface $possibleCommand */
            $possibleCommand = $this->commandLocator->get($commandInfo['possibleCommand']);

            return $possibleCommand->getHelpMessage();
        } elseif (!$this->commandLocator->has($commandInfo['command'])) {
            throw new \RuntimeException('Unknown command');
        }

        try {
            $commandName = $commandInfo['command'];
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
                new SmsProcessedEvent($user, $commandName, $commandInfo['arguments'], $message),
                SmsProcessedEvent::NAME
            );
        } catch (ServiceNotFoundException $e) {
            $this->logger->warning('Unknown command', ['user' => $user, 'command' => $commandName]);
            $message = $this->translator->trans(
                'Error. The command %badCommand% does not exist. If you need help, send: %helpCommand%',
                [
                    '%badCommand%' => $commandName,
                    '%helpCommand%' => 'HELP'
                ]
            );
        } catch (ValidationException $e) {
            $this->logger->warning(
                'Validation error',
                ['user' => $user, 'command' => $commandName, 'exception' => $e]
            );
            $message = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error executing command',
                ['user' => $user, 'command' => $commandName, 'exception' => $e]
            );
            $message = $this->translator->trans('An error occurred while processing your request.');
        }

        return $message;
    }
}
