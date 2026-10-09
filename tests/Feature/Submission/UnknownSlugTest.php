<?php

use App\Models\Space;

/*
 * Unknown, soft-deleted, and tombstoned slugs all 404. The route uses
 * Space::where('slug', ...)->live()->first(), and the live() scope
 * excludes both soft-deleted rows and (after retention) tombstones.
 */

test('unknown slug returns 404', function () {
    $this->get('/s/never-existed')->assertNotFound();
});

test('soft-deleted slug returns 404', function () {
    $space = Space::factory()->create(['deleted_at' => now()->subDays(2)]);
    $this->get("/s/{$space->slug}")->assertNotFound();
});

test('tombstoned slug (soft-deleted past retention) returns 404', function () {
    $space = Space::factory()->create(['deleted_at' => now()->subDays(45)]);
    $this->get("/s/{$space->slug}")->assertNotFound();
});
