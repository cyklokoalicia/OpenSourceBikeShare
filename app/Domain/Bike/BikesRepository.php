<?php
namespace BikeShare\Domain\Bike;

use BikeShare\Domain\Core\Repository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BikesRepository extends Repository
{

    public function model()
    {
        return Bike::class;
    }

    public function findByBikeNum($bikeNum, $columns = ['*'])
    {
        return $this->findBy('bike_num', $bikeNum, $columns);
    }


    public function create(array $data)
    {
        $model = new Bike($data);
        $model->status = BikeStatus::SETTING;
        $model->save();

        return $model;
    }
}
