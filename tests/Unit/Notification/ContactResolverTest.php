<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Notification;

use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Channel;
use BikeShare\Notification\ContactResolver;
use BikeShare\Notification\RecipientsInterface;
use BikeShare\Notification\UserRecipient;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ContactResolverTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserSettingsRepository&MockObject $settingsRepository;
    private ContactResolver $resolver;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->settingsRepository = $this->createMock(UserSettingsRepository::class);
        $this->resolver = new ContactResolver($this->userRepository, $this->settingsRepository);
    }

    public function testAdminsWithExclusion(): void
    {
        $this->userRepository->method('findAdmins')->willReturn([
            ['userId' => '1', 'number' => '421111', 'mail' => 'a1@example.com'],
            ['userId' => '2', 'number' => '421222', 'mail' => 'a2@example.com'],
            ['userId' => '3', 'number' => '421333', 'mail' => 'a3@example.com'],
        ]);
        $this->settingsRepository->method('findByUserId')->willReturnCallback(
            static fn(int $id): array => ['locale' => $id === 2 ? 'sk' : 'de']
        );

        $contacts = $this->resolver->resolve(new AdminRecipients(1));

        $this->assertCount(2, $contacts);
        $this->assertSame(2, $contacts[0]->userId);
        $this->assertSame('sk', $contacts[0]->locale);
        $this->assertSame('a2@example.com', $contacts[0]->addressFor(Channel::Email));
        $this->assertSame('421222', $contacts[0]->addressFor(Channel::Sms));
        $this->assertSame(3, $contacts[1]->userId);
        $this->assertSame('de', $contacts[1]->locale);
        $this->assertSame(['email' => 'a3@example.com', 'sms' => '421333'], $contacts[1]->addresses);
    }

    public function testEmptyMailGivesNoEmailAddress(): void
    {
        $this->userRepository->method('findAdmins')->willReturn([
            ['userId' => 1, 'number' => '421111', 'mail' => ''],
        ]);
        $this->settingsRepository->method('findByUserId')->willReturn(['locale' => 'en']);

        $contacts = $this->resolver->resolve(new AdminRecipients());

        $this->assertCount(1, $contacts);
        $this->assertNull($contacts[0]->addressFor(Channel::Email));
        $this->assertSame('421111', $contacts[0]->addressFor(Channel::Sms));
        $this->assertSame(['sms' => '421111'], $contacts[0]->addresses);
    }

    public function testUserRecipient(): void
    {
        $this->userRepository->expects($this->once())->method('findItem')->with(5)->willReturn(
            ['userId' => '5', 'number' => '421555', 'mail' => 'u5@example.com']
        );
        $this->settingsRepository->method('findByUserId')->with(5)->willReturn(['locale' => 'uk']);

        $contacts = $this->resolver->resolve(new UserRecipient(5));

        $this->assertCount(1, $contacts);
        $this->assertSame(5, $contacts[0]->userId);
        $this->assertSame('uk', $contacts[0]->locale);
        $this->assertSame('u5@example.com', $contacts[0]->addressFor(Channel::Email));
    }

    public function testUnknownUserGivesNoContacts(): void
    {
        $this->userRepository->method('findItem')->willReturn(null);

        $this->assertSame([], $this->resolver->resolve(new UserRecipient(99)));
    }

    public function testUnsupportedRecipientsThrow(): void
    {
        $this->expectException(\LogicException::class);

        $this->resolver->resolve(new class implements RecipientsInterface {
        });
    }
}
