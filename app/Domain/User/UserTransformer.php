<?php
namespace BikeShare\Domain\User;

use BikeShare\Domain\Bike\BikeTransformer;
use BikeShare\Domain\Rent\RentTransformer;
use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{

    public $availableIncludes = [
        'bikes',
        'rents',
        'activeRents'
    ];


    public function transform(User $user)
    {
        return [
            'uuid'           => (string)$user->uuid,
            'name'           => $user->name,
            'phone_number'   => $user->phone_number,
            'email'          => $user->email,
            'note'           => $user->note,
            'credit'         => $user->credit,
            'recommendation' => $user->recommendation,
            'limit'          => $user->limit,
            'locked'         => $user->locked,
            'created_at'     => (string)$user->created_at,
        ];
    }


    public function includeBikes(User $user)
    {
        $bikes = $user->bikes;

        return $this->collection($bikes, new BikeTransformer());
    }


    public function includeRents(User $user)
    {
        $rents = $user->rents;

        return $this->collection($rents, new RentTransformer());
    }


    public function includeActiveRents(User $user)
    {
        $rents = $user->activeRents;

        return $this->collection($rents, new RentTransformer());
    }
}
