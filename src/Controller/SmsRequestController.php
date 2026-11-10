<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsCommand\CommandExecutor;
use BikeShare\SmsConnector\SmsConnectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsRequestController extends AbstractController
{
    public function __construct(
        private readonly PhonePurifierInterface $phonePurifier,
        private readonly SmsConnectorInterface $smsConnector,
        private readonly SmsSenderInterface $smsSender,
        private readonly UserProvider $userProvider,
        private readonly LoggerInterface $logger,
        private readonly NotifierInterface $notifier,
        private readonly CommandExecutor $commandExecutor,
        private readonly UserSettingsRepository $userSettingsRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        $this->smsConnector->receive();
        $number = $this->smsConnector->getNumber();
        $sms = $this->smsConnector->getProcessedMessage();

        if (!$this->phonePurifier->isValid($number)) {
            $this->logger->error(
                "Invalid phone number",
                ["number" => $number, 'sms' => $this->smsConnector]
            );

            return new Response("Invalid phone number", Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($number);
        } catch (UserNotFoundException) {
            $this->logger->error(
                "User not found",
                ["number" => $number, 'sms' => $this->smsConnector]
            );

            return new Response("User not found", Response::HTTP_BAD_REQUEST);
        }

        $userSettings = $this->userSettingsRepository->findByUserId($user->getUserId());

        try {
            $reply = $this->commandExecutor->execute($sms, $user);
        } catch (\Throwable $exception) {
            $this->logger->error(
                "Error processing SMS",
                [
                    "number" => $number,
                    'sms' => $sms,
                    'exception' => $exception
                ]
            );
            $this->notifier->notify(
                new Notification(
                    NotificationType::SmsProblem,
                    new TranslatableMessage('notification.subject.sms_problem'),
                    new TranslatableMessage('admin.notification.sms_problem', ['number' => $number, 'sms' => $sms])
                ),
                new AdminRecipients()
            );

            return new Response($this->smsConnector->respond());
        }

        try {
            $this->smsSender->send($number, $reply, $userSettings['locale']);
        } catch (\Throwable $exception) {
            // The command already ran: keep its result and hand the reply to the admins.
            $this->logger->error(
                'SMS reply not delivered',
                ['number' => $number, 'sms' => $sms, 'exception' => $exception]
            );
            $this->notifier->notify(
                new Notification(
                    NotificationType::SmsReplyFailed,
                    new TranslatableMessage('notification.subject.sms_reply_failed'),
                    new TranslatableMessage(
                        'admin.notification.sms_reply_failed',
                        [
                            'number' => $number,
                            'sms' => $sms,
                            'reply' => $this->renderReply($reply, $userSettings['locale']),
                        ]
                    )
                ),
                new AdminRecipients()
            );
        }

        return new Response($this->smsConnector->respond());
    }

    /**
     * The send may have failed while translating, so rendering here must not throw:
     * an exception would turn into a 500 and make the gateway redeliver the command.
     */
    private function renderReply(TranslatableInterface $reply, string $locale): string
    {
        try {
            return $reply->trans($this->translator, $locale);
        } catch (\Throwable) {
            return $reply instanceof TranslatableMessage ? $reply->getMessage() : get_debug_type($reply);
        }
    }
}
