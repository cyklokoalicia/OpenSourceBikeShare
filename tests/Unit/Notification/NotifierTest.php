<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Notification;

use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Channel;
use BikeShare\Notification\ChannelSenderInterface;
use BikeShare\Notification\Contact;
use BikeShare\Notification\ContactResolver;
use BikeShare\Notification\Notification;
use BikeShare\Notification\Notifier;
use BikeShare\Notification\NotificationType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatableMessage;

class NotifierTest extends TestCase
{
    private ContactResolver&Stub $resolver;
    private LoggerInterface&MockObject $logger;
    private array $calls = [];
    private array $failOn = [];

    protected function setUp(): void
    {
        $this->resolver = $this->createStub(ContactResolver::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->calls = [];
        $this->failOn = [];
    }

    public function testSendsOnAllowedChannelsWithAddress(): void
    {
        $this->resolver->method('resolve')->willReturn([
            new Contact(1, 'en', ['email' => 'a@x.com', 'sms' => '111']),
            new Contact(2, 'sk', ['email' => 'b@x.com', 'sms' => '222']),
        ]);
        $this->logger->expects($this->never())->method('error');

        $this->notifier()->notify($this->notification(NotificationType::BikeNoteAdded), new AdminRecipients());

        $this->assertSame([
            ['email', 'a@x.com', 'en'],
            ['sms', '111', 'en'],
            ['email', 'b@x.com', 'sk'],
            ['sms', '222', 'sk'],
        ], $this->calls);
    }

    public function testSkipsChannelWithoutAddress(): void
    {
        $this->resolver->method('resolve')->willReturn([new Contact(1, 'en', ['email' => 'a@x.com'])]);
        $this->logger->expects($this->never())->method('error');

        $this->notifier()->notify($this->notification(NotificationType::BikeNoteAdded), new AdminRecipients());

        $this->assertSame([['email', 'a@x.com', 'en']], $this->calls);
    }

    public function testSkipsDisallowedChannel(): void
    {
        $this->resolver->method('resolve')->willReturn([
            new Contact(1, 'en', ['email' => 'a@x.com', 'sms' => '111']),
        ]);
        $this->logger->expects($this->never())->method('error');

        $this->notifier()->notify($this->notification(NotificationType::StackWatch), new AdminRecipients());

        $this->assertSame([['email', 'a@x.com', 'en']], $this->calls);
    }

    public function testFailingDeliveryIsIsolated(): void
    {
        $this->resolver->method('resolve')->willReturn([
            new Contact(1, 'en', ['email' => 'a@x.com', 'sms' => '111']),
            new Contact(2, 'sk', ['email' => 'b@x.com', 'sms' => '222']),
        ]);
        $this->failOn = ['a@x.com'];
        $this->logger->expects($this->once())->method('error')->with(
            'Notification delivery failed',
            $this->callback(
                static fn(array $context): bool => $context['type'] === 'bike-note-added'
                    && $context['channel'] === 'email'
                    && $context['userId'] === 1
                    && $context['exception'] instanceof \RuntimeException
                    && count($context) === 4
            )
        );

        $this->notifier()->notify($this->notification(NotificationType::BikeNoteAdded), new AdminRecipients());

        $this->assertSame([
            ['email', 'a@x.com', 'en'],
            ['sms', '111', 'en'],
            ['email', 'b@x.com', 'sk'],
            ['sms', '222', 'sk'],
        ], $this->calls);
    }

    public function testFailingRecipientLookupIsIsolated(): void
    {
        $resolver = $this->createStub(ContactResolver::class);
        $resolver->method('resolve')->willThrowException(new \RuntimeException('db down'));
        $this->resolver = $resolver;
        $this->logger->expects($this->once())->method('error')->with(
            'Notification delivery failed',
            $this->callback(
                static fn(array $context): bool => $context['type'] === 'bike-note-added'
                    && $context['channel'] === null
                    && $context['userId'] === null
                    && $context['exception'] instanceof \RuntimeException
                    && count($context) === 4
            )
        );

        $this->notifier()->notify($this->notification(NotificationType::BikeNoteAdded), new AdminRecipients());

        $this->assertSame([], $this->calls);
    }

    public function testSkipsChannelWithoutRegisteredSender(): void
    {
        $this->resolver->method('resolve')->willReturn([
            new Contact(1, 'en', ['email' => 'a@x.com', 'sms' => '111']),
        ]);
        $this->logger->expects($this->never())->method('error');

        $this->notifier([Channel::Email])->notify(
            $this->notification(NotificationType::BikeNoteAdded),
            new AdminRecipients()
        );

        $this->assertSame([['email', 'a@x.com', 'en']], $this->calls);
    }

    public function testNoContactsNoSends(): void
    {
        $this->resolver->method('resolve')->willReturn([]);
        $this->logger->expects($this->never())->method('error');

        $this->notifier()->notify($this->notification(NotificationType::BikeNoteAdded), new AdminRecipients(1));

        $this->assertSame([], $this->calls);
    }

    private function notification(NotificationType $type): Notification
    {
        return new Notification($type, new TranslatableMessage('some.key'));
    }

    /**
     * @param list<Channel> $registered
     */
    private function notifier(array $registered = [Channel::Email, Channel::Sms]): Notifier
    {
        $senders = [];
        foreach ($registered as $channel) {
            $senders[$channel->value] = $this->spy($channel);
        }
        $locator = $this->createStub(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(static fn(string $id): bool => isset($senders[$id]));
        $locator->method('get')->willReturnCallback(static fn(string $id) => $senders[$id]);

        return new Notifier($this->resolver, $locator, $this->logger);
    }

    private function spy(Channel $channel): ChannelSenderInterface
    {
        $test = $this;

        return new class ($channel, $test) implements ChannelSenderInterface {
            public function __construct(private Channel $channel, private NotifierTest $test)
            {
            }

            public static function getChannelName(): string
            {
                return 'spy';
            }

            public function send(string $address, Notification $notification, ?string $locale): void
            {
                $this->test->record($this->channel->value, $address, $locale);
            }
        };
    }

    public function record(string $channel, string $address, ?string $locale): void
    {
        $this->calls[] = [$channel, $address, $locale];
        if ($channel === 'email' && in_array($address, $this->failOn, true)) {
            throw new \RuntimeException('boom');
        }
    }
}
