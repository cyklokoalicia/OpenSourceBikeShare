<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsCommand\CommandExecutor;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsRequestController extends AbstractController
{
    private SmsConnectorInterface $smsConnector;
    private SmsSenderInterface $smsSender;
    private UserProvider $userProvider;
    private LoggerInterface $logger;
    private AdminNotifier $adminNotifier;
    private TranslatorInterface $translator;
    private CommandExecutor $commandExecutor;

    public function __construct(
        SmsConnectorInterface $smsConnector,
        SmsSenderInterface $smsSender,
        UserProvider $userProvider,
        LoggerInterface $logger,
        AdminNotifier $adminNotifier,
        TranslatorInterface $translator,
        CommandExecutor $commandExecutor
    ) {
        $this->smsConnector = $smsConnector;
        $this->smsSender = $smsSender;
        $this->userProvider = $userProvider;
        $this->logger = $logger;
        $this->adminNotifier = $adminNotifier;
        $this->translator = $translator;
        $this->commandExecutor = $commandExecutor;
    }

    /**
     * @Route("/receive.php", name="sms_request")
     * @Route("/sms/receive.php", name="sms_request_old")
     */
    public function index(): Response
    {
        $this->smsConnector->receive();

        try {
            $user = $this->userProvider->loadUserByIdentifier($this->smsConnector->getNumber());
        } catch (UserNotFoundException $e) {
            $this->logger->error(
                "User not found",
                ["number" => $this->smsConnector->getNumber(), 'sms' => $this->smsConnector]
            );

            return new Response("User not found");
        }

        try {
            $message = $this->commandExecutor->execute($this->smsConnector->getProcessedMessage(), $user);
            $this->smsSender->send($this->smsConnector->getNumber(), $message);
        } catch (\Throwable $exception) {
            $this->logger->error(
                "Error processing SMS",
                [
                    "number" => $this->smsConnector->getNumber(),
                    'sms' => $this->smsConnector->getProcessedMessage(),
                    'exception' => $exception
                ]
            );
            $this->adminNotifier->notify(
                $this->translator->trans('Problem with SMS') . ': '
                    . $this->smsConnector->getNumber() . '-' . $this->smsConnector->getProcessedMessage(),
                false
            );
        }

        return new Response($this->smsConnector->respond());
    }
}
