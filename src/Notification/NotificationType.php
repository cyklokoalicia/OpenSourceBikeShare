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
    case BikeRevertedOwner = 'bike-reverted-owner';

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
            self::LongRentalUser,
            self::BikeRevertedOwner => [Channel::Sms],
        };
    }
}
