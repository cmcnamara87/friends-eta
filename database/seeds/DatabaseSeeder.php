<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        // $this->call(UserTableSeeder::class);
        $craig = \App\User::create(['name' => 'Craig', 'email' => 'craig@asd.com']);
        $craigLocation = \App\Location::create(['user_id' => 1, 'lat' => -27.47101, 'long' => 153.02345]);
        $kelvin = \App\User::create(['name' => 'Kelvin', 'email' => 'kel@asd.com']);
        $kelvinLocation = \App\Location::create(['user_id' => 2, 'lat' => -27.37593, 'long' => 153.12095]);
        $jamal = \App\User::create(['name' => 'Jamal', 'email' => 'jamal@asd.com']);
        $jamalLocation = \App\Location::create(['user_id' => 3, 'lat' => -26.37593, 'long' => 152.12095]);
        
        Model::reguard();
    }
}
