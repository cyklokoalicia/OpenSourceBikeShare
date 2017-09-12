<?php

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Stand\Stand;
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

/**
 * @param array $standAttributes
 * @param array $bikeAttributes
 * @return array [$stand, $bike]
 */
function standWithBike($standAttributes = [], $bikeAttributes = [])
{
    $stand = create(Stand::class, $standAttributes);
    $bike = $stand->bikes()->save(make(Bike::class, $bikeAttributes));
    return [$stand, $bike];
}

/**
 * @param array $standAttributes
 * @param array $bikesAttributes
 * @return array [$bikePos0, $$bikePos1]
 */
function twoBikesOnStand($standAttributes = [], $bikesAttributes = [])
{
    $stand = create(Stand::class);
    $bikePos0 = $stand->bikes()->save(make(Bike::class, ['stack_position' => 0]));
    $bikePos1 = $stand->bikes()->save(make(Bike::class, ['stack_position' => 1]));

    return [$bikePos0, $bikePos1];
}