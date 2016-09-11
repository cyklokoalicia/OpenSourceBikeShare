<?php
namespace BikeShare\Domain\User;

use BikeShare\Domain\Core\Repository;
use BikeShare\Http\Services\AppConfig;

class UsersRepository extends Repository
{

    public function model()
    {
        return User::class;
    }

    public function getUsersWithRole($role)
    {
        return $this->model->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }


    public function create(array $data)
    {
        $user = new User($data);
        $user->limit = 1;
        if (app('AppConfig')->isCreditEnabled()) {
            $user->credit = 0;
        }
        $user->locked = 0;
        $user->save();
        if (isset($data['roles'])) {
            $user->assignRole($data['roles']);
        }

        return $user;
    }
}
