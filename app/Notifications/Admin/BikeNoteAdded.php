<?php

namespace BikeShare\Notifications\Admin;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Note\Note;
use BikeShare\Domain\User\User;
use BikeShare\Notifications\AdminNotification;

class BikeNoteAdded extends AdminNotification
{
    /**
     * @var Bike
     */
    private $bike;
    /**
     * @var Note
     */
    private $note;
    /**
     * @var User
     */
    private $reportedBy;

    public function __construct(Bike $bike, Note $note, User $reportedBy)
    {
        $this->bike = $bike;
        $this->note = $note;
        $this->reportedBy = $reportedBy;
    }

    public function smsText()
    {
        return "Note #{$this->note->id} b. {$this->bike->bike_num} {$this->bike->status} ".
            "by {$this->reportedBy->name} ({$this->reportedBy->phone_number}): {$this->note->note}";
    }
}
