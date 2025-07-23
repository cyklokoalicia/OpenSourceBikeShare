<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\RegistrationRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationEventListener
{
    private string $appName;
    private string $systemRules;
    private RegistrationRepository $registrationRepository;
    private MailSenderInterface $mailSender;
    private TranslatorInterface $translator;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        string $appName,
        string $systemRules,
        RegistrationRepository $registrationRepository,
        MailSenderInterface $mailSender,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->appName = $appName;
        $this->systemRules = $systemRules;
        $this->registrationRepository = $registrationRepository;
        $this->mailSender = $mailSender;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
    }

    public function __invoke(UserRegistrationEvent $event): void
    {
        $user = $event->getUser();

        $subject = $this->translator->trans('Registration');

        $userId = $user->getUserId();
        $emailRecipient = $user->getEmail();
        $userKey = hash('sha256', md5(mt_rand() . microtime() . $emailRecipient));

        $this->registrationRepository->addItem($userId, $userKey);

        $names = preg_split("/[\s,]+/", $user->getUsername());
        $firstName = $names[0];
        $message = $this->translator->trans(
            'success.registration.mail',
            [
                'name' => $firstName,
                'systemName' => $this->appName,
                'systemRulesPageUrl' => $this->systemRules,
                'emailConfirmURL' => $this->urlGenerator->generate(
                    'user_confirm_email',
                    ['key' => $userKey],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ]
        );

        $this->mailSender->sendMail($emailRecipient, $subject, $message);
    }
}
