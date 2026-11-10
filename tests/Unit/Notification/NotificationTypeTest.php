<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Notification;

use BikeShare\Notification\Channel;
use BikeShare\Notification\NotificationType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotificationTypeTest extends TestCase
{
    #[DataProvider('typeProvider')]
    public function testAllowedChannelsMatchTodaysBySms(NotificationType $type, array $expected): void
    {
        $this->assertSame($expected, $type->allowedChannels());
    }

    public static function typeProvider(): iterable
    {
        $both = [Channel::Email, Channel::Sms];
        $email = [Channel::Email];
        $sms = [Channel::Sms];

        yield 'bike note added' => [NotificationType::BikeNoteAdded, $both];
        yield 'stack watch' => [NotificationType::StackWatch, $email];
        yield 'too many rents' => [NotificationType::TooManyRents, $both];
        yield 'sms command audit' => [NotificationType::SmsCommandAudit, $both];
        yield 'new verified user' => [NotificationType::NewVerifiedUser, $email];
        yield 'sms problem' => [NotificationType::SmsProblem, $email];
        yield 'duplicate sms' => [NotificationType::DuplicateSms, $email];
        yield 'long rental admin' => [NotificationType::LongRentalAdmin, $both];
        yield 'long rental user' => [NotificationType::LongRentalUser, $sms];
        yield 'inactive bikes' => [NotificationType::InactiveBikes, $email];
        yield 'bike reverted owner' => [NotificationType::BikeRevertedOwner, $sms];
        yield 'registration' => [NotificationType::Registration, $email];
        yield 'reconfirmation' => [NotificationType::Reconfirmation, $email];
        yield 'password reset' => [NotificationType::PasswordReset, $email];
    }
}
