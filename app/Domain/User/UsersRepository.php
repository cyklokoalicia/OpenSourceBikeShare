<?php
namespace BikeShare\Domain\User;

use App;
use BikeShare\Domain\Core\Repository;
use Illuminate\Support\Str;

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


    public function getConfirmationToken()
    {
        return hash_hmac('sha256', Str::random(40), $this->hashKey());
    }


    private function hashKey()
    {
        $key = config('app.key');

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
    }
}
