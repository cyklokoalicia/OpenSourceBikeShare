<?php

use BikeShare\Domain\User\Roles;
use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use Spatie\Permission\Models\Role;

function create($class, $attributes = [], $times = null)
{
    return factory($class, $times)->create($attributes);
}
function make($class, $attributes = [], $times = null)
{
    return factory($class, $times)->make($attributes);
}

/**
 * Helper method - create user with enough resources to rent bikes
 * @param array $attributes
 * @param int $numberOfBikesHeCanRent
 * @return mixed
 */
function userWithResources($attributes = [], $isAdmin=false, $numberOfBikesHeCanRent = 1000)
{
    $user = create(User::class, array_merge([
        'credit' => app(AppConfig::class)->getRequiredCredit() * $numberOfBikesHeCanRent,
        'limit' => $numberOfBikesHeCanRent
    ], $attributes));

    if ($isAdmin){
        $user->assignRole(Role::findByName(Roles::ADMIN));
    }

    return $user;
}