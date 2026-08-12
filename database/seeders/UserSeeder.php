<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'senior_zoro',
            'email' => 'vicabucot1234@gmail.com',
            'password' => bcrypt('20030228'),
        ]);
    }
}
