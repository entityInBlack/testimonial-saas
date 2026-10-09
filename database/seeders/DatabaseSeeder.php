<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Step 1 base seeders: four users on the Free plan.
     * No Spaces, no testimonials — those come in Step 8 (per
     * data-model §1 / §15, the full fixture is Maya, Dev with 2
     * Spaces at 100/12 cap-exercising, etc.).
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            ['name' => 'Maya Sharma', 'email' => 'maya@brightcopy.co'],
            ['name' => 'Dev Okafor', 'email' => 'dev@shiplog.io'],
            ['name' => 'Priya Raman', 'email' => 'priya@nimbus.dev'],
            ['name' => 'Ben Fischer', 'email' => 'ben@example.test'],
        ];

        foreach ($users as $u) {
            User::factory()->create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => $password,
            ]);
        }
    }
}
