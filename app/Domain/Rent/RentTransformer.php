<?php
namespace BikeShare\Domain\Rent;

use BikeShare\Domain\Bike\BikeTransformer;
use BikeShare\Domain\Stand\StandTransformer;
use BikeShare\Domain\User\UserTransformer;
use League\Fractal\TransformerAbstract;

class RentTransformer extends TransformerAbstract
{

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [
        'bike',
        'standFrom',
        'standTo',
        'user'
    ];


    public function transform(Rent $rent)
    {
        return [
            'uuid'       => (string)$rent->uuid,
            'status'     => $rent->status,
            'started_at' => (string)$rent->started_at,
            'ended_at'   => (string)$rent->ended_at,
        ];
    }


    public function includeBike(Rent $rent)
    {
        $bike = $rent->bike;

        return $this->item($bike, new BikeTransformer);
    }


    public function includeStandFrom(Rent $rent)
    {
        if ($standFrom = $rent->standFrom) {
            return $this->item($standFrom, new StandTransformer);
        }
    }


    public function includeStandTo(Rent $rent)
    {
        if ($standTo = $rent->standTo) {
            return $this->item($standTo, new StandTransformer);
        }
    }

    public function includeUser(Rent $rent)
    {
        if ($user = $rent->user) {
            return $this->item($user, new UserTransformer());
        }
    }
}
