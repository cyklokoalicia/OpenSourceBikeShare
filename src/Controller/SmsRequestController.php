<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Entity\User;
use BikeShare\App\Kernel;
use BikeShare\App\Security\UserProvider;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsCommand\SmsCommandInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class SmsRequestController extends AbstractController
{
    private Kernel $kernel;
    private SmsConnectorInterface $smsConnector;
    private SmsSenderInterface $smsSender;
    private ServiceLocator $commandLocator;
    private UserProvider $userProvider;
    private Translator $translator;
    private LoggerInterface $logger;

    public function __construct(
        Kernel $kernel,
        SmsConnectorInterface $smsConnector,
        SmsSenderInterface $smsSender,
        ServiceLocator $commandLocator,
        UserProvider $userProvider,
        Translator $translator,
        LoggerInterface $logger
    ) {
        $this->kernel = $kernel;
        $this->smsConnector = $smsConnector;
        $this->smsSender = $smsSender;
        $this->commandLocator = $commandLocator;
        $this->userProvider = $userProvider;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * @Route("/receive.php", name="sms_request")
     * @Route("/sms/receive.php", name="sms_request")
     */
    public function index(): Response
    {
        $this->smsConnector->receive();

        $user = $this->userProvider->loadUserByIdentifier($this->smsConnector->getNumber());
        if (is_null($user)) {
            $this->logger->error(
                "Invalid number",
                ["number" => $this->smsConnector->getNumber(), 'sms' => $this->smsConnector]
            );

            return new Response("Invalid number");
        }

        //preg_split must be used instead of explode because of multiple spaces
        $args = preg_split("/\s+/", $this->smsConnector->getProcessedMessage());
        $commandName = strtoupper($args[0] ?? '');

        if ($this->commandLocator->has($commandName)) {
            $this->tryExecuteCommand($commandName, $user, $args);
        } else {
            $kernel = $this->kernel;
            $sms = $this->smsConnector;

            ob_start();
            require_once $this->getParameter('kernel.project_dir') . '/receive.php';
            $content = ob_get_clean();
        }

        return new Response($this->smsConnector->respond());
    }

    private function tryExecuteCommand(string $commandName, User $user, array $args): void
    {
        try {
            /* @var SmsCommandInterface $command */
            $command = $this->commandLocator->get($commandName);
            $message = $command->execute($user, $args);
        } catch (ServiceNotFoundException $e) {
            $this->logger->warning('Unknown command', ['user' => $user, 'command' => $commandName]);
            $message = $this->translator->trans(
                'Error. The command %badCommand% does not exist. If you need help, send: %helpCommand%',
                [
                    '%badCommand%' => $commandName,
                    '%helpCommand%' => 'HELP'
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Error executing command',
                ['user' => $user, 'command' => $commandName, 'exception' => $e->getMessage()]
            );
            $message = $this->translator->trans('An error occurred while processing your request.');
        }

        $this->smsSender->send($this->smsConnector->getNumber(), $message);
    }
}
