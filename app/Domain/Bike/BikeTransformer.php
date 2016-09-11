<?php
namespace BikeShare\Domain\Bike;

use BikeShare\Domain\Note\NoteTransformer;
use BikeShare\Domain\Stand\StandTransformer;
use BikeShare\Domain\User\UserTransformer;
use BikeShare\Http\Controllers\Stands\StandsController;
use League\Fractal\TransformerAbstract;

class BikeTransformer extends TransformerAbstract
{

    public $availableIncludes = [
        'user',
        'stand',
        'notes'
    ];


    public function transform(Bike $bike)
    {
        return [
            'uuid'         => (string)$bike->uuid,
            'bike_num'     => $bike->bike_num,
            'note'         => $bike->note,
            'status'       => $bike->status,
            'current_code' => $bike->current_code,
        ];
    }


    public function includeStand(Bike $bike)
    {
        $stand = $bike->stand;

        return $this->item($stand, new StandTransformer());
    }


    public function includeUser(Bike $bike)
    {
        $user = $bike->user;

        return $this->item($user, new UserTransformer());
    }


    public function includeNotes(Bike $bike)
    {
        $notes = $bike->notes;

        return $this->collection($notes, new NoteTransformer());
    }
}
