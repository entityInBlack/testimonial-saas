<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Step 1 base seeders (4 Free users) + Step 8 fixture seeders
 * (Maya + Dev with their Spaces/testimonials/embed config/
 * open deletion request). Everything runs from one entry point
 * so `php artisan migrate --seed` produces a working demo and
 * the tests can `$this->seed(DatabaseSeeder::class)` to land on
 * the same baseline.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BaseUsersSeeder::class,
            FixtureSeeder::class,
        ]);
    }
}
