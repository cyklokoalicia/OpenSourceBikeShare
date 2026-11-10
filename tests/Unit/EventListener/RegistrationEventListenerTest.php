<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\EventListener;

use BikeShare\App\Entity\User;
use BikeShare\Event\UserRegistrationEvent;
use BikeShare\EventListener\RegistrationEventListener;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\UserRecipient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatableMessage;

class RegistrationEventListenerTest extends TestCase
{
    public function testNotifiesTheNewUser(): void
    {
        /** @var UrlGeneratorInterface&MockObject $urlGenerator */
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('user_confirm_email', ['key' => 'k123'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://example.com/user/confirm/email/k123');

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())
            ->method('notify')
            ->with(
                $this->equalTo(new Notification(
                    NotificationType::Registration,
                    new TranslatableMessage('Registration'),
                    new TranslatableMessage('success.registration.mail', [
                        'name' => 'John',
                        'systemName' => 'My Bikes',
                        'systemRulesPageUrl' => 'https://example.com/rules',
                        'emailConfirmURL' => 'https://example.com/user/confirm/email/k123',
                    ])
                )),
                $this->equalTo(new UserRecipient(42))
            );

        $user = new User(
            42,
            '421900111222',
            'john@example.com',
            '',
            'City',
            'John Doe',
            0,
            false,
            new \DateTimeImmutable(),
        );

        $listener = new RegistrationEventListener('My Bikes', 'https://example.com/rules', $urlGenerator, $notifier);
        $listener(new UserRegistrationEvent($user, 'k123'));
    }
}
