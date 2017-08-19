<?php
namespace BikeShare\Domain\Rent;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Core\Repository;
use BikeShare\Domain\User\User;

class RentsRepository extends Repository
{

    public function model()
    {
        return Rent::class;
    }

    public function findOpenRent(User $user, Bike $bike)
    {
        return $this->findWhere(['user_id'=>$user->id, 'bike_id'=>$bike->id, 'status' => RentStatus::OPEN])->first();
    }
}
