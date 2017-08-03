<?php

use BikeShare\Domain\User\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    const USER_PHONE_NUM = '1111';
    const ADMIN_PHONE_NUM = '9999';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);

        factory(User::class)
            ->create(['phone_number' => self::ADMIN_PHONE_NUM])
            ->assignRole($adminRole);

        factory(User::class)
            ->create(['phone_number' => self::USER_PHONE_NUM])
            ->assignRole($userRole);
    }
}
