<?php

use BikeShare\Domain\Bike\Bike;
use BikeShare\Domain\Bike\BikeStatus;
use BikeShare\Domain\Stand\Stand;
use Illuminate\Database\Seeder;

class StandsWithBikesSeeder extends Seeder
{
    function __construct(Faker\Generator $faker)
    {
        $this->faker = $faker;
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(Stand::class, 50)
            ->create()
            ->each(function ($stand){
                $bikesCount = rand(0,5);

                for ($i=1; $i<=$bikesCount; $i++){
                    $bike = factory(Bike::class)->make(['stack_position'=>$i]);
                    // 10% chance of bike being broken
                    if (rand(1,10) <= 1){
                        $bike = factory(Bike::class)->states('broken')->make(['stack_position'=>$i]);
                    }
                    $stand->bikes()->save($bike);
                }
            });
    }
}
