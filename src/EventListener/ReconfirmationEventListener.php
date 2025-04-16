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
    private RegistrationRepository $registrationRepository;
    private MailSenderInterface $mailSender;
    private TranslatorInterface $translator;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(
        RegistrationRepository $registrationRepository,
        MailSenderInterface $mailSender,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger
    ) {
        $this->registrationRepository = $registrationRepository;
        $this->mailSender = $mailSender;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
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
                'mailSenderClass' => get_class($this->mailSender),
            ]
        );
        $this->mailSender->sendMail($emailRecipient, $subject, $message);
    }
}
