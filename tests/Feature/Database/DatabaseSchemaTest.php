<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('all v1 tables exist', function () {
    foreach (['users', 'spaces', 'testimonials', 'embed_configurations', 'deletion_requests'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("table `$table` is missing");
    }
});

test('spaces has the v1 column set (data-model §3.2)', function () {
    foreach (['user_id', 'name', 'slug', 'public_id', 'title', 'ask', 'theme', 'rating_enabled', 'field_config'] as $col) {
        expect(Schema::hasColumn('spaces', $col))->toBeTrue("spaces.$col missing");
    }
});

test('testimonials has the v1 column set (data-model §3.3)', function () {
    foreach ([
        'space_id', 'name', 'email', 'address',
        'company_name', 'social_url', 'profile_photo',
        'testimonial', 'rating',
        'consent_given', 'consented_at', 'consent_text_version',
        'is_favorite', 'is_wall_of_love', 'is_hidden',
        'is_public', 'submitted_at',
    ] as $col) {
        expect(Schema::hasColumn('testimonials', $col))->toBeTrue("testimonials.$col missing");
    }
});

test('users table does not have a consent column (v1 has no Billable trait)', function () {
    // v1 ships Free only. There is NO `consent_*` column on the users table —
    // consent lives on the testimonial, not on the customer.
    foreach (['consent', 'consents', 'consent_given', 'consent_at'] as $col) {
        expect(Schema::hasColumn('users', $col))->toBeFalse("users.$col should not exist in v1");
    }
});

test('embed_configurations has exactly the §3.4 column set', function () {
    foreach (['space_id', 'layout', 'dark_mode', 'animation_enabled', 'background_color', 'item_limit', 'show_rating'] as $col) {
        expect(Schema::hasColumn('embed_configurations', $col))->toBeTrue("embed_configurations.$col missing");
    }
    foreach (['show_photo', 'show_social_handle', 'max_items', 'display_options'] as $col) {
        expect(Schema::hasColumn('embed_configurations', $col))->toBeFalse("embed_configurations.$col should not exist in v1");
    }
});

test('deletion_requests has the §3.6 column set', function () {
    foreach (['email', 'space_slug', 'space_id', 'testimonial_id', 'status', 'acted_at'] as $col) {
        expect(Schema::hasColumn('deletion_requests', $col))->toBeTrue("deletion_requests.$col missing");
    }
});

test('embed_configurations is 1:1 with spaces (unique on space_id)', function () {
    $indexes = collect(DB::select("SHOW INDEX FROM embed_configurations"))
        ->pluck('Key_name')
        ->unique();

    expect($indexes->contains('uniq_embed_space'))->toBeTrue(
        'unique(space_id) missing — got: '.implode(', ', $indexes->all())
    );
});

test('testimonials has idx_public, idx_space_live, idx_respondents', function () {
    $indexes = collect(DB::select('SHOW INDEX FROM testimonials'))
        ->pluck('Key_name')
        ->unique();

    expect($indexes->contains('idx_public'))->toBeTrue();
    expect($indexes->contains('idx_space_live'))->toBeTrue();
    expect($indexes->contains('idx_respondents'))->toBeTrue();
});

test('spaces has unique(slug), unique(public_id), index(user_id, deleted_at)', function () {
    $indexes = collect(DB::select('SHOW INDEX FROM spaces'))
        ->pluck('Key_name')
        ->unique();

    expect($indexes->contains('spaces_slug_unique'))->toBeTrue('unique(slug) missing — got: '.implode(', ', $indexes->all()));
    expect($indexes->contains('spaces_public_id_unique'))->toBeTrue('unique(public_id) missing — got: '.implode(', ', $indexes->all()));
    expect($indexes->contains('idx_spaces_user_deleted'))->toBeTrue('index(user_id, deleted_at) missing — got: '.implode(', ', $indexes->all()));
});