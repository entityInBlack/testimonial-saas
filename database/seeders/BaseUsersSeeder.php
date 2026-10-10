<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The four Free users from Step 1.
 *
 * Lives in its own file so PSR-4 autoloading can find it (a class
 * declared alongside DatabaseSeeder in a single file is not
 * autoloadable). `php artisan db:seed --class=BaseUsersSeeder` and
 * `$this->call([BaseUsersSeeder::class])` both work.
 *
 * Each row is created with firstOrCreate() — the id is stable across
 * re-runs — and is given `password` (Hash::make) and a non-null
 * `email_verified_at` so the four Step 1 / Step 8 users can log in
 * and hit the dashboard without a verification round-trip.
 */
class BaseUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $verifiedAt = now();

        $users = [
            ['name' => 'Maya Sharma', 'email' => 'maya@brightcopy.co'],
            ['name' => 'Dev Okafor',  'email' => 'dev@shiplog.io'],
            ['name' => 'Priya Raman', 'email' => 'priya@nimbus.dev'],
            ['name' => 'Ben Fischer', 'email' => 'ben@example.test'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => $password,
                    'email_verified_at' => $verifiedAt,
                ],
            );
        }
    }
}
