<?php

use Illuminate\Support\Facades\DB;

test('testimonials table has a STORED GENERATED is_public column', function () {
    // information_schema returns columns uppercased; we lowercase the
    // keys so the assertion is portable across MySQL versions.
    $columns = DB::select(
        'SELECT * FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ?',
        [DB::getDatabaseName(), 'testimonials']
    );

    $rows = collect($columns)->map(fn ($r) => (object) array_change_key_case((array) $r, CASE_LOWER));
    $generated = $rows->firstWhere('column_name', 'is_public');

    expect($generated)->not->toBeNull('is_public column is missing from testimonials');
    expect($generated->extra)->toContain('STORED GENERATED');
});

test('is_public is 1 only when all four conditions are met (data-model §3.3 row fixture)', function () {
    // Insert via the SQL layer so the generated column updates on every row.
    // We bypass Eloquent to keep the test focused on schema behaviour.

    $userId = DB::table('users')->insertGetId([
        'name' => 'Schema Test',
        'email' => 'schema-test-'.uniqid().'@example.test',
        'password' => bcrypt('password'),
    ]);

    $spaceId = DB::table('spaces')->insertGetId([
        'user_id' => $userId,
        'name' => 'Schema Test Space',
        'slug' => 'schema-test-'.uniqid(),
        'public_id' => strtolower(\Illuminate\Support\Str::random(12)),
        'title' => 't',
        'ask' => 'a',
        'theme' => 'minimal_light',
        'rating_enabled' => 1,
        'field_config' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mk = function (string $name, array $overrides) use ($spaceId) {
        return DB::table('testimonials')->insertGetId(array_merge([
            'space_id' => $spaceId,
            'name' => $name,
            'email' => $name.'-'.uniqid().'@e.test',
            'address' => '1 Test St',
            'testimonial' => 'test',
            'consent_given' => 1,
            'is_wall_of_love' => 1,
            'is_hidden' => 0,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    };

    // Data-model §3.3 fixture rows #41..#45:
    $priya = $mk('Priya', []);                       // all green -> is_public=1
    $tom   = $mk('Tom', ['is_wall_of_love' => 0]);   // not on wall -> 0
    $sara  = $mk('Sara', ['consent_given' => 0]);    // no consent -> 0
    $ben   = $mk('Ben', ['is_hidden' => 1]);         // hidden -> 0 (publish intact)
    $ana   = $mk('Ana', ['deleted_at' => now()]);    // soft-deleted -> 0

    expect((int) DB::table('testimonials')->where('id', $priya)->value('is_public'))->toBe(1);
    expect((int) DB::table('testimonials')->where('id', $tom)->value('is_public'))->toBe(0);
    expect((int) DB::table('testimonials')->where('id', $sara)->value('is_public'))->toBe(0);
    expect((int) DB::table('testimonials')->where('id', $ben)->value('is_public'))->toBe(0);
    expect((int) DB::table('testimonials')->where('id', $ana)->value('is_public'))->toBe(0);
});