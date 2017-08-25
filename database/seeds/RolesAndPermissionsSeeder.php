<?php

use BikeShare\Domain\Bike\BikePermissions;
use BikeShare\Domain\Stand\StandPermissions;
use BikeShare\Domain\User\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // create permissions
        $permissions = array_merge(StandPermissions::textValues(), BikePermissions::textValues());
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // create roles and assign existing permissions
        $admin = Role::create(['name' => Roles::ADMIN]);
        $admin->givePermissionTo($permissions);

        $user = Role::create(['name' => Roles::USER]);
        $user->givePermissionTo(BikePermissions::RENT);
        $user->givePermissionTo(BikePermissions::RETURN);
        $user->givePermissionTo(BikePermissions::WHERE);
        $user->givePermissionTo(StandPermissions::ADD_NOTE);
    }
}