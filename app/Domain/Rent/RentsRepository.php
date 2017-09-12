<?php
namespace BikeShare\Domain\Rent;

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Core\Repository;
use BikeShare\Domain\User\User;

class RentsRepository extends Repository
{

    public function model()
    {
        return Rent::class;
    }

    public function findOpenRent(Bike $bike)
    {
        if ($bike->status !== BikeStatus::OCCUPIED || !$bike->user){
            return null;
        }
        return $this->findWhere(['user_id'=>$bike->user->id, 'bike_id'=>$bike->id, 'status' => RentStatus::OPEN])->first();
    }
}
