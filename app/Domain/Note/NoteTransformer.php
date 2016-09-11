<?php
namespace BikeShare\Domain\Note;

use BikeShare\Domain\Bike\BikeTransformer;
use BikeShare\Domain\User\UserTransformer;
use League\Fractal\TransformerAbstract;

class NoteTransformer extends TransformerAbstract
{

    public $availableIncludes = [
        'bike',
        'user',
    ];

    public function transform(Note $note)
    {
        return [
            'uuid' => (string)$note->uuid,
            'note' => (string)$note->note,
            'created_at' => (string)$note->created_at,
        ];
    }

    public function includeUser(Note $note)
    {
        $user = $note->user;

        return $this->item($user, new UserTransformer());
    }

    public function includeBike(Note $note)
    {
        $bike = $note->bike;

        return $this->item($bike, new BikeTransformer());
    }
}
