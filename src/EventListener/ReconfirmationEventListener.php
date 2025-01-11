<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\App\Configuration;
use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\RegistrationRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReconfirmationEventListener
{
    private RegistrationRepository $registrationRepository;
    private MailSenderInterface $mailSender;
    private TranslatorInterface $translator;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        string $appName,
        RegistrationRepository $registrationRepository,
        Configuration $configuration,
        MailSenderInterface $mailSender,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->appName = $appName;
        $this->registrationRepository = $registrationRepository;
        $this->configuration = $configuration;
        $this->mailSender = $mailSender;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
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
                    'user_confirm',
                    ['key' => $userKey],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ]
        );

        $this->mailSender->sendMail($emailRecipient, $subject, $message);
    }
}
