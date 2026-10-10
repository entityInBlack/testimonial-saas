<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Step 6 — nightly scheduled jobs.
 *
 * Times are pinned and intentional:
 *   - purge:run at 03:30 — runs while traffic is lowest; withoutOverlapping
 *     guarantees a slow prior run does not stack a second purge in the
 *     same window.
 *   - backup:run at 04:00 — an hour after purge, so a backup is always
 *     a copy of the post-purge state, not the pre-purge state.
 *
 * withoutOverlapping is set on both: the second one would either wait
 * for the first to release the mutex or skip the new run.
 */
Schedule::command('purge:run')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('backup:run')
    ->dailyAt('04:00')
    ->withoutOverlapping();