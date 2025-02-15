<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsCommand\CommandExecutor;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SmsRequestController extends AbstractController
{
    private SmsConnectorInterface $smsConnector;
    private SmsSenderInterface $smsSender;
    private UserProvider $userProvider;
    private LoggerInterface $logger;
    private CommandExecutor $commandExecutor;

    public function __construct(
        SmsConnectorInterface $smsConnector,
        SmsSenderInterface $smsSender,
        UserProvider $userProvider,
        LoggerInterface $logger,
        CommandExecutor $commandExecutor
    ) {
        $this->smsConnector = $smsConnector;
        $this->smsSender = $smsSender;
        $this->userProvider = $userProvider;
        $this->logger = $logger;
        $this->commandExecutor = $commandExecutor;
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

        $message = $this->commandExecutor->execute($this->smsConnector->getProcessedMessage(), $user);
        $this->smsSender->send($this->smsConnector->getNumber(), $message);

        return new Response($this->smsConnector->respond());
    }
}
