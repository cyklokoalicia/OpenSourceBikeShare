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
        if (is_array($role)) {
            return $this->model->whereHas('roles', function ($q) use ($role) {
                $q->whereIn('name', $role);
            });
        }

        return $this->model->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }


    public function create(array $data)
    {
        $data['limit'] = 1;
        if (app('AppConfig')->isCreditEnabled()) {
            $data['credit'] = 0;
        }
        $data['locked'] = 0;
        $user = parent::create($data);

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
