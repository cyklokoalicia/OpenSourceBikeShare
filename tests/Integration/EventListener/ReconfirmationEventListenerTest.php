<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\EventListener;

use BikeShare\App\Entity\User;
use BikeShare\Event\UserReconfirmationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use BikeShare\Test\Support\MailAssert;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\Translation\TranslatableMessage;

class ReconfirmationEventListenerTest extends BikeSharingKernelTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const USER_KEY = 'reconfirmkey123';

    protected function tearDown(): void
    {
        // The throttle lives in a filesystem pool that outlives the test; do not leak it into other runs.
        $userId = (int)self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER)['userId'];
        self::getContainer()->get('cache.reconfirmation_throttle')->deleteItem('reconfirmation_email.' . $userId);

        parent::tearDown();
    }

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
        $this->assertSame(
            '/user/confirm/email/' . self::USER_KEY,
            MailAssert::confirmationLink($mail, 'email.confirmation.mail')
        );
        $this->assertSame('Eve', $mail['message']->getParameters()['name']);
        $this->assertSame($user->getEmail(), $mail['recipient']);

        // Second dispatch within the window is throttled — no additional email.
        $eventDispatcher->dispatch($event);
        $this->assertCount(1, $mailSender->getSentMessages(), 'Reconfirmation email should be throttled');
    }

    public function testFailedSendStillThrottles(): void
    {
        $user = $this->buildUser();
        $this->expectLog(
            Logger::ERROR,
            '/Notification delivery failed/',
            ['type' => 'reconfirmation', 'channel' => 'email', 'userId' => $user->getUserId()]
        );
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
