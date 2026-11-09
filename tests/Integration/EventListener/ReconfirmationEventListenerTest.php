<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\EventListener;

use BikeShare\App\Entity\User;
use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\Translation\TranslatableMessage;

class ReconfirmationEventListenerTest extends BikeSharingKernelTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const USER_KEY = 'reconfirmkey123';

    public function testReconfirmationEmailUsesEventUserKeyAndIsThrottled(): void
    {
        $user = $this->buildUser();
        $cache = self::getContainer()->get('cache.reconfirmation_throttle');
        $cache->deleteItem('reconfirmation_email.' . $user->getUserId());

        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $mailSender = self::getContainer()->get(MailSenderInterface::class);

        $event = new UserReconfirmationEvent($user, self::USER_KEY);

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
        $this->assertSame($user->getEmail(), $mail['recipient']);
        $this->assertStringEndsWith('/user/confirm/email/' . self::USER_KEY, $params['emailConfirmURL']);

        // Second dispatch within the window is throttled — no additional email.
        $eventDispatcher->dispatch($event);
        $this->assertCount(1, $mailSender->getSentMessages(), 'Reconfirmation email should be throttled');
    }

    public function testFailedSendStillThrottles(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');
        $user = $this->buildUser();
        $cache = self::getContainer()->get('cache.reconfirmation_throttle');
        $cache->deleteItem('reconfirmation_email.' . $user->getUserId());

        $mailSender = self::getContainer()->get(MailSenderInterface::class);
        $mailSender->failFor($user->getEmail());

        $event = new UserReconfirmationEvent($user, self::USER_KEY);
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch($event);
        $this->assertTrue(
            $cache->getItem('reconfirmation_email.' . $user->getUserId())->isHit(),
            'A failed send must still leave the throttle in place'
        );
        $eventDispatcher->dispatch($event);

        $this->assertCount(0, $mailSender->getSentMessages());
        $failures = array_filter(
            $this->logHandler->getRecords(),
            static fn (LogRecord $r): bool => $r->level->value === Logger::ERROR
                && preg_match('/Notification delivery failed/', $r->message) === 1
        );
        $this->assertCount(1, $failures, 'The second dispatch must be throttled, not retried');
    }

    private function buildUser(): User
    {
        $row = self::getContainer()->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        return new User(
            (int) $row['userId'],
            $row['number'],
            $row['mail'],
            'hash',
            'Default City',
            'Eve Doe',
            0,
            false,
            new \DateTimeImmutable('2026-01-01 00:00:00'),
        );
    }
}
