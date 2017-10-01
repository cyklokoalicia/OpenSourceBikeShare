<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (App::environment('local')) {
            $this->call(UsersSeeder::class);
            $this->call(StandsWithBikesSeeder::class);
        }
    }
}
