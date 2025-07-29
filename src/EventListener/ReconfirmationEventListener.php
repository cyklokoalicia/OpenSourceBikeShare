<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\RegistrationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReconfirmationEventListener
{
    public function __construct(
        private readonly RegistrationRepository $registrationRepository,
        private readonly MailSenderInterface $mailSender,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserReconfirmationEvent $event): void
    {
        $user = $event->getUser();

        $subject = $this->translator->trans('Email confirmation');

        $userId = $user->getUserId();
        $emailRecipient = $user->getEmail();

        $registration = $this->registrationRepository->findItemByUserId($userId);
        $userKey = $registration['userKey'];

        $names = preg_split("/[\s,]+/", $user->getUsername());
        $firstName = $names[0];
        $message = $this->translator->trans(
            'email.confirmation.mail',
            [
                'name' => $firstName,
                'emailConfirmURL' => $this->urlGenerator->generate(
                    'user_confirm_email',
                    ['key' => $userKey],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ]
        );

        $this->logger->notice(
            'Sending reconfirmation email',
            [
                'userId' => $userId,
                'email' => $emailRecipient,
                'mailSenderClass' => $this->mailSender::class,
            ]
        );
        $this->mailSender->sendMail($emailRecipient, $subject, $message);
    }
}
