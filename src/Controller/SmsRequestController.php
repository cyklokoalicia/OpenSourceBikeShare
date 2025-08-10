<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsCommand\CommandExecutor;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsRequestController extends AbstractController
{
    public function __construct(
        private readonly PhonePurifierInterface $phonePurifier,
        private readonly SmsConnectorInterface $smsConnector,
        private readonly SmsSenderInterface $smsSender,
        private readonly UserProvider $userProvider,
        private readonly LoggerInterface $logger,
        private readonly AdminNotifier $adminNotifier,
        private readonly TranslatorInterface $translator,
        private readonly CommandExecutor $commandExecutor,
    ) {
    }

    #[Route('/receive.php', name: 'sms_request')]
    #[Route('/sms/receive.php', name: 'sms_request_old')]
    public function index(): Response
    {
        $this->smsConnector->receive();

        if (!$this->phonePurifier->isValid($this->smsConnector->getNumber())) {
            $this->logger->error(
                "Invalid phone number",
                ["number" => $this->smsConnector->getNumber(), 'sms' => $this->smsConnector]
            );

            return new Response("Invalid phone number");
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($this->smsConnector->getNumber());
        } catch (UserNotFoundException) {
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
