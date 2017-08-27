<?php
namespace BikeShare\Domain\User;

use App;
use BikeShare\Domain\Core\Repository;
use BikeShare\Http\Services\AppConfig;
use Illuminate\Support\Str;

class UsersRepository extends Repository
{

    public function model()
    {
        return User::class;
    }

    public function getAdmins()
    {
        return $this->getUsersWithRole(Roles::ADMIN);
    }

    public function getUsersWithRole($role)
    {
        return User::role($role)->get();
    }

    public function create(array $data)
    {
        $data['limit'] = 1;
        if (app(AppConfig::class)->isCreditEnabled()) {
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
