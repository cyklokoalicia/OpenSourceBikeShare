<?php

namespace BikeShare\Notifications\Admin;

use BikeShare\Domain\Note\Note;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\AdminNotification;

class StandNoteAdded extends AdminNotification
{
    /**
     * @var Note
     */
    private $note;
    /**
     * @var User
     */
    private $reportedBy;
    /**
     * @var Stand
     */
    private $stand;

    public function __construct(Stand $stand, Note $note, User $reportedBy)
    {
        $this->note = $note;
        $this->reportedBy = $reportedBy;
        $this->stand = $stand;
    }

    public function smsText()
    {
        return "Note #{$this->note->id} on stand {$this->stand->name} ".
            "by {$this->reportedBy->name} ({$this->reportedBy->phone_number}): {$this->note->note}";
    }
}
