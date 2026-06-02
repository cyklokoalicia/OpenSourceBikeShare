<?php

declare(strict_types=1);

namespace BikeShare\EventListener;

use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Mail\MailSenderInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReconfirmationEventListener
{
    public function __construct(
        private readonly MailSenderInterface $mailSender,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(UserReconfirmationEvent $event): void
    {
        $user = $event->getUser();
        $userId = $user->getUserId();

        // Throttle: the email checker runs on every authenticated API request, so an
        // unconfirmed caller could otherwise trigger one reconfirmation email per request.
        // Send at most one per user per window regardless of how often the event fires.
        $throttleItem = $this->cache->getItem('reconfirmation_email.' . $userId);
        if ($throttleItem->isHit()) {
            return;
        }
        // Expiry comes from the pool's default lifetime (cache.reconfirmation_throttle).
        $throttleItem->set(true);
        $this->cache->save($throttleItem);

        $subject = $this->translator->trans('Email confirmation');
        $emailRecipient = $user->getEmail();

        $names = preg_split("/[\s,]+/", $user->getUsername());
        $firstName = $names[0];
        $message = $this->translator->trans(
            'email.confirmation.mail',
            [
                'name' => $firstName,
                'emailConfirmURL' => $this->urlGenerator->generate(
                    'user_confirm_email',
                    ['key' => $event->getUserKey()],
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
