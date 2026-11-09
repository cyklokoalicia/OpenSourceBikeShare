<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\EventListener;

use BikeShare\App\Entity\User;
use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ReconfirmationEventListenerTest extends BikeSharingKernelTestCase
{
    private const USER_ID = 987654;
    private const USER_KEY = 'reconfirmkey123';

    public function testReconfirmationEmailUsesEventUserKeyAndIsThrottled(): void
    {
        $cache = self::getContainer()->get('cache.reconfirmation_throttle');
        $cache->deleteItem('reconfirmation_email.' . self::USER_ID);

        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $mailSender = self::getContainer()->get(MailSenderInterface::class);

        $event = new UserReconfirmationEvent($this->buildUser(), self::USER_KEY);

        // First dispatch sends the email, building the confirm URL purely from the event's
        // userKey (no registration re-query — so a concurrently deleted row cannot crash it).
        $eventDispatcher->dispatch($event);
        $this->assertCount(1, $mailSender->getSentMessages());
        $mail = $mailSender->getSentMessages()[0];
        $this->assertEquals(new TranslatableMessage('Email confirmation'), $mail['subject']);
        $this->assertInstanceOf(TranslatableMessage::class, $mail['message']);
        $this->assertSame('email.confirmation.mail', $mail['message']->getMessage());
        $params = $mail['message']->getParameters();
        $this->assertSame('Eve', $params['name']);
        $this->assertStringEndsWith('/user/confirm/email/' . self::USER_KEY, $params['emailConfirmURL']);

        // Second dispatch within the window is throttled — no additional email.
        $eventDispatcher->dispatch($event);
        $this->assertCount(1, $mailSender->getSentMessages(), 'Reconfirmation email should be throttled');
    }

    private function buildUser(): User
    {
        return new User(
            self::USER_ID,
            '+421900111222',
            'reconfirm_' . self::USER_ID . '@example.com',
            'hash',
            'Default City',
            'Eve Doe',
            0,
            false,
            new \DateTimeImmutable('2026-01-01 00:00:00'),
        );
    }
}
