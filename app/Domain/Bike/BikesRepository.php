<?php

namespace BikeShare\Domain\Bike;

use BikeShare\Domain\Core\Repository;
use BikeShare\Domain\User\User;

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


    public function bikesRentedByUserCount(User $user)
    {
        return $this->model->where(['user_id' => $user->id, 'status' => BikeStatus::OCCUPIED])->count();
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
