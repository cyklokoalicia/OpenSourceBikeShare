<?php

declare(strict_types=1);

namespace BikeShare\Event;

use Symfony\Contracts\EventDispatcher\Event;

class SmsDuplicateDetectedEvent extends Event
{
    public const NAME = 'sms.duplicate.detected';

    private string $smsUuid;

    public function __construct(
        string $smsUuid
    ) {
        $this->smsUuid = $smsUuid;
    }

    public function getSmsUuid(): string
    {
        return $this->smsUuid;
    }
}
