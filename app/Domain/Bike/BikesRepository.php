<?php

namespace BikeShare\Domain\Bike;

use BikeShare\Domain\Core\Repository;

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
        $data['status'] = BikeStatus::SETTING;
        $model = parent::create($data);

        return $model;
    }


    public function formatData(array $data): array
    {
        unset($data['_token']);
        unset($data['_method']);

        return $data;
    }
}
