<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * Step 6 — scheduler entries.
 *
 * Asserts the schedule registers:
 *   - purge:run at 03:30 with withoutOverlapping
 *   - backup:run at 04:00 with withoutOverlapping
 *   - The cron expressions are exactly the pinned values
 */

test('schedule contains purge:run with the pinned 03:30 cron and withoutOverlapping', function () {
    $schedule = app(Schedule::class);

    $purgeEvent = collect($schedule->events())
        ->first(fn ($e) => str_contains($e->command, 'purge:run'));

    expect($purgeEvent)->not->toBeNull();
    expect($purgeEvent->expression)->toBe('30 3 * * *');
    expect($purgeEvent->withoutOverlapping)->toBeTrue();
});

test('schedule contains backup:run with the pinned 04:00 cron and withoutOverlapping', function () {
    $schedule = app(Schedule::class);

    $backupEvent = collect($schedule->events())
        ->first(fn ($e) => str_contains($e->command, 'backup:run'));

    expect($backupEvent)->not->toBeNull();
    expect($backupEvent->expression)->toBe('0 4 * * *');
    expect($backupEvent->withoutOverlapping)->toBeTrue();
});
