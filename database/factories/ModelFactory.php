<?php

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Stand\Stand;
use BikeShare\Domain\User\User;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$factory->define(User::class, function (Faker\Generator $faker) {
    static $password;

    return [
        'name' => $faker->name,
        'email' => $faker->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
        'phone_number' => $faker->phoneNumber,
        'credit' => rand(10,100)
    ];
});

$factory->define(Stand::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->firstName,
        'description' => $faker->paragraph,
        'place_name' => $faker->streetName,
        // locations in Bratislava
        'longitude' => $faker->longitude(17.0732198, 17.135304),
        'latitude' => $faker->latitude(48.096330, 48.1594594)
    ];
});

$factory->define(Bike::class, function (Faker\Generator $faker) {
    return [
        'bike_num' => $faker->unique()->numberBetween(1, 500),
        'current_code' => Bike::generateBikeCode(),
        'status' => BikeStatus::FREE,
    ];
});

$factory->state(Bike::class, 'broken', function (Faker\Generator $faker) {
    return [
        'status' => BikeStatus::BROKEN,
        'note' => $faker->sentence
    ];
});
