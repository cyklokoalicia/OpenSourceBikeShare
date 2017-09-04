<?php

namespace BikeShare\Notifications\Admin;

use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\AdminNotification;

class AllNotesDeleted extends AdminNotification
{
    private $pattern;
    private $deletedCount;
    private $user;
    private $stand;

    public function __construct(User $user, $pattern, $deletedCount, Stand $stand)
    {
        $this->pattern = $pattern;
        $this->deletedCount = $deletedCount;
        $this->user = $user;
        $this->stand = $stand;
    }

    public function smsText()
    {
        $txt = "{$this->deletedCount} note(s) for bikes on stand {$this->stand->name} deleted by {$this->user->name}";
        return (empty($this->pattern) ? "All " : "") . $txt;
    }
}
