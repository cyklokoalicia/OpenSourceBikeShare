<?php

declare(strict_types=1);

namespace BikeShare\Notification;

enum NotificationType: string
{
    case BikeNoteAdded = 'bike-note-added';
    case StackWatch = 'stack-watch';
    case TooManyRents = 'too-many-rents';
    case SmsCommandAudit = 'sms-command-audit';
    case NewVerifiedUser = 'new-verified-user';
    case SmsProblem = 'sms-problem';
    case DuplicateSms = 'duplicate-sms';
    case LongRentalAdmin = 'long-rental-admin';
    case LongRentalUser = 'long-rental-user';
    case InactiveBikes = 'inactive-bikes';

    /**
     * @return list<Channel>
     */
    public function allowedChannels(): array
    {
        return match ($this) {
            self::BikeNoteAdded,
            self::TooManyRents,
            self::SmsCommandAudit,
            self::LongRentalAdmin => [Channel::Email, Channel::Sms],
            self::StackWatch,
            self::NewVerifiedUser,
            self::SmsProblem,
            self::DuplicateSms,
            self::InactiveBikes => [Channel::Email],
            self::LongRentalUser => [Channel::Sms],
        };
    }

    /**
     * Translation key of the subject, for channels that have one.
     */
    public function subjectKey(): string
    {
        return match ($this) {
            self::BikeNoteAdded => 'notification.subject.bike-note-added',
            self::StackWatch => 'notification.subject.stack-watch',
            self::TooManyRents => 'notification.subject.too-many-rents',
            self::SmsCommandAudit => 'notification.subject.sms-command-audit',
            self::NewVerifiedUser => 'notification.subject.new-verified-user',
            self::SmsProblem => 'notification.subject.sms-problem',
            self::DuplicateSms => 'notification.subject.duplicate-sms',
            self::LongRentalAdmin => 'notification.subject.long-rental-admin',
            self::LongRentalUser => 'notification.subject.long-rental-user',
            self::InactiveBikes => 'notification.subject.inactive-bikes',
        };
    }
}
