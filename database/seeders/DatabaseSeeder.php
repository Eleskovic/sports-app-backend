<?php

namespace Database\Seeders;

use App\Models\Meditation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    const MEDS_SEED_COUNT = 150;
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $faker = \Faker\Factory::create();
        $seed  = [];
        for ($i=0;$i<self::MEDS_SEED_COUNT;$i++) {
            $randDuration = rand(10,30) * 60; // assuming a meditation session would be between 10 and 30 minutes here.
            $start = $faker->dateTimeBetween('-6 months')->getTimestamp(); // putting -6 months here.
            // we are in March so it would probably be separating records almost equally
            $end   = $start + $randDuration;
            $seed[] = [
                'user_id'    => 1, // inserting all records on behalf of User:1
                'duration'   => $randDuration,
                'started_at' => date('Y-m-d H:i:s', $start),
                'ended_at'   => date('Y-m-d H:i:s', $end),
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        Meditation::insert($seed);


    }
}
